<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Campsite", its stay requests and group enquiries: the
 * stay request, the request's own page with its messages, My Stay
 * Requests and the staff's list; the Group Stays page, the group enquiry,
 * the enquiry's own page, My Group Enquiries and the staff's list; the
 * privacy page the consent boxes link, the e-mails of both forms and the
 * new message e-mail every thread of the site sends, and the widgets that
 * draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A stay request is a request, never a reservation: a record of the Stay
 * Requests form. A guest without an account gets one (auto-registration),
 * so the request's page and My Stay Requests show it at once. Its Status
 * is an office-use-only field the reception changes in the control panel;
 * the offer and the place held are written under it as messages (the
 * page's comments), mailed to the guest and to the park. The template
 * keeps no pitch calendar and no prices per date.
 *
 * The kind of stay, the place and the season start with what a link
 * sends (?kind= from the stay-kind pages, ?unit= and ?kind= from a unit's
 * page, ?season= from the prices table), each the answer's own words as
 * parts.php writes them. A group enquiry is a record of its own form the
 * same way; its kind of group starts with ?kind= from the Group Stays
 * cards, and a programme or a list of tents a group sends is kept in the
 * template's private folder.
 *
 * The helpers of this file are named st_… (stay requests) and gr_…
 * (groups), so they never take the place of a helper another page file
 * of the folder defines in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$stay_pages = array();

// The sample request the canvas shows. Its dates are words, never a
// date: a fixed day would read as a past season a year later.
$st_sample_kind = $kinds['touring']['title'];
$st_sample_unit = $units['hardstanding']['name'];
$st_sample_season = $seasons['mid']['title'];
$st_sample_notes = lang('We travel with a caravan of seven metres and an awning; a pitch with some shade near the play area would be lovely.');
$st_sample_arrival = lang('Arrival date');
$st_sample_departure = lang('Departure date');
$st_season_choices = array_merge($season_titles, array(lang('Not sure yet')));
$st_missing = lang('This stay request could not be found, or it is not yours to see.');

// The kinds of group the park welcomes, each with the enquiry opened for
// it (?kind=, one of $group_kinds).
$gr_cards = array(
    array('icon' => 'bi-backpack', 'kind' => $group_kinds[0], 'title' => lang('Schools and Youth Groups'),
        'text' => lang('Class trips and scout camps: a field of their own, the campfire circle and activities led by our team.')),
    array('icon' => 'bi-people', 'kind' => $group_kinds[1], 'title' => lang('Clubs and Societies'),
        'text' => lang('Walking, cycling or fishing clubs: pitches side by side, a room for the evening and packed lunches on request.')),
    array('icon' => 'bi-truck', 'kind' => $group_kinds[2], 'title' => lang('Caravan Rallies'),
        'text' => lang('A meadow of touring pitches with hook-ups, a marquee for the get-together and a rally rate per outfit.')),
    array('icon' => 'bi-balloon-heart', 'kind' => $group_kinds[3], 'title' => lang('Family Celebrations'),
        'text' => lang('A birthday, a reunion or a wedding in the meadow: cabins and glamping tents for the family, a long table by the lake.')),
);
$gr_sample_note = lang('A class of twenty-four pupils and three teachers, in our own tents, for three nights in early summer; we would like a canoe morning on the lake.');
$gr_missing = lang('This group enquiry could not be found, or it is not yours to see.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the stay request: what happens next, then the
// park's times and its link.
$st_aside = function () use ($el, $heading, $link, $check_list, $park) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('The reception answers there within a day, with an offer and the place held for you.'),
        lang('Confirm it in a message; the status shows Stay confirmed once the place is yours.'),
        lang('Nothing is paid now: the stay is paid at the reception.'),
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
            $check_list(array($park['arrival'], $park['hours'], $park['opening']), 'list-unstyled small mb-3'),
            $link(lang('See the Prices'), '{{page:prices}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// The side column of the group enquiry.
$gr_aside = function () use ($el, $heading, $check_list) {
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
            $check_list(array(
                lang('We answer within two working days with an offer for the whole group.'),
                lang('Groups of ten or more get a field or a row of places of their own.'),
                lang('The date is held for you while you decide.'),
                lang('Nothing is paid now.'),
            ), 'list-unstyled small mb-0'),
        )),
    );
};

// The card of My Stay Requests or My Group Enquiries that starts another.
$st_new_card = function ($icon_name, $title, $text, $button, $page_key) use ($el, $icon, $span, $para, $link) {
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
$st_form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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

// ── Pages: stay requests ────────────────────────────────────────────────

// The stay request. What is sent opens the request's own page.
$stay_pages[] = array(
    'key'              => 'book',
    'name'             => lang('request-a-stay'),
    'folder'           => 'public',
    'title'            => lang('Request a Stay'),
    'meta_description' => lang('Tell us your dates and how you camp: the reception answers within a day with an offer and the place held for you.'),
    'form'             => array(
        'form_name'            => lang('Stay Requests'),
        'confirmation_message' => lang('Thank you, your stay request has reached us. The reception answers within a day.'),
        'confirmation_page_id' => '{{tab:request_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New stay request: ^^stay_kind^^'),
        'notify_page_id'       => '{{tab:email_request_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your stay request'),
        'confirm_page_id'      => '{{tab:email_request_received}}',
    ),
    'tree'             => $st_form_page(lang('Request a Stay'),
        lang('Tell us your dates and how you camp. The reception answers within a day with an offer and the place held for you; nothing is paid now.'),
        'request_form', $st_aside()),
);

// One stay request: the request, its status and the messages under it.
$stay_pages[] = array(
    'key'      => 'request_view',
    'name'     => lang('stay-request'),
    'folder'   => 'registration',
    'title'    => lang('Stay Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => false,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your stay request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('request_detail'),
        $comments_section(lang('Messages'), 'request_messages'),
    ), false),
);

// My Stay Requests: the guest's own requests (for the staff, everybody's).
$stay_pages[] = array(
    'key'     => 'my_requests',
    'name'    => lang('my-stay-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Stay Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Stay Requests'), lang('Your stay requests, their status and the reception\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_requests_list')), '', '8'),
                    $col(array($st_new_card('bi-calendar-plus', lang('Another stay?'),
                        lang('Ask for other dates or another place; your details are filled in for you.'), lang('Request a Stay'), 'book')), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every stay request, newest first, with a search.
$stay_pages[] = array(
    'key'     => 'staff_requests',
    'name'    => lang('all-stay-requests'),
    'folder'  => 'private',
    'title'   => lang('Stay Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Stay Requests'), lang('Every stay request, newest first: answer with an offer, set the status and write to the guest under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_requests_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$stay_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the campsite keeps about you when you ask for a stay or a group visit, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the campsite keeps about you when you ask for a stay or a group visit, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you ask for a stay or a group visit, join the newsletter or open an account, we keep your name, your contact details, your dates and what you tell us about your stay, to answer you, hold your place and welcome you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('cp-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails of the stay request: what the reception receives and the
// reply to the guest.
$stay_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_new',
    'name'  => lang('email-new-stay-request'),
    'title' => lang('New Stay Request E-mail'),
    'tree'  => $mail_page(array($widget('email_request_admin'))),
));
$stay_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_received',
    'name'  => lang('email-stay-request-received'),
    'title' => lang('Stay Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_request_client'))),
));
// A new message or reply: sent to the guest of a stay request or a group
// enquiry, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$stay_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your stay request, your group enquiry, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Pages: groups ───────────────────────────────────────────────────────

$gr_card_nodes = array();
foreach ($gr_cards as $g) {
    $gr_card_nodes[] = $col(array(
        $el('div', 'pcp-lift h-100 p-4 rounded-4 border bg-body', lang('Group Card'), array(
            $icon_tile($g['icon'], 'mb-3'),
            $heading('h3', $g['title'], 'h5 fw-semibold mb-2'),
            $para($g['text'], 'text-body-secondary mb-3'),
            $link(lang('Ask for this group'), '{{page:group_enquiry}}?' . $query(array('kind' => $g['kind'])), 'fw-semibold link-primary text-decoration-none'),
        )),
    ), '6', '3');
}

// Group Stays: who comes in groups, how an enquiry goes and the questions
// asked before one.
$stay_pages[] = array(
    'key'              => 'groups',
    'name'             => lang('group-stays'),
    'folder'           => 'public',
    'title'            => lang('Group Stays'),
    'meta_description' => lang('School trips, club weekends, caravan rallies and family celebrations at the campsite: what we arrange and how to ask.'),
    'tree'             => $page(array(
        $page_header(lang('Group Stays'),
            lang('A class, a club, a rally or the whole family: tell us how many you are and when, and we plan the stay with you.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Send a Group Enquiry'), '{{page:group_enquiry}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('My Group Enquiries'), '{{page:my_enquiries}}', 'btn btn-outline-primary rounded-pill px-4'),
            )))),
        $el('section', 'py-5', lang('Groups'), array(
            $container(array(
                $section_header(lang('Who comes in groups'), lang('Room for everyone')),
                $row($gr_card_nodes, '4'),
            )),
        ), $searchable('cp-groups', lang('group, school trip, scouts, club, rally, caravan club, reunion, wedding, party'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('How it works'), lang('From the first question to the campfire')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us about the group'), lang('How many you are, the dates, how you would like to sleep and what you would like to do.')),
                            $step(2, lang('Get an offer'), lang('Within two working days: the places, the activities and the price for the group, on your enquiry\'s page.')),
                            $step(3, lang('Plan it together'), lang('We hold the dates while you decide, then plan the details with you under your enquiry.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $section_header(lang('Good to know'), lang('Before you ask')),
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Is there a group rate?'),
                                lang('Yes. Groups of ten or more pay a rate per person and night that we write in the offer; teachers and leaders stay free.'), lang('group rate, discount, leaders free'), 'cp-groups-faq'),
                            $faq(2, lang('Can a group bring its own tents?'),
                                lang('Of course. A group camps on a field of its own near a washing block; we can add glamping tents or cabins for those who would rather sleep in a bed.'), lang('own tents, field, glamping, cabins'), 'cp-groups-faq'),
                            $faq(3, lang('What about quiet hours?'),
                                lang('Groups keep the park rules like every guest: quiet from ten in the evening, campfires only in the fire pits.'), lang('quiet hours, rules, campfire'), 'cp-groups-faq'),
                        ), array('id' => 'cp-groups-faq')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cp-groups-how', lang('group offer, group rate, own tents, quiet hours'))),
    )),
);

// The group enquiry. What is sent opens the enquiry's own page.
$stay_pages[] = array(
    'key'              => 'group_enquiry',
    'name'             => lang('group-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Group Enquiry'),
    'meta_description' => lang('Ask about a stay for a school, a club, a rally or a family celebration: we answer within two working days with an offer.'),
    'form'             => array(
        'form_name'            => lang('Group Enquiries'),
        'confirmation_message' => lang('Thank you, your group enquiry has reached us. We answer within two working days with an offer.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New group enquiry: ^^group_kind^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your group enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $st_form_page(lang('Group Enquiry'),
        lang('Tell us about your group, the dates and how you would like to sleep. We answer within two working days with an offer.'),
        'enquiry_form', $gr_aside()),
);

// One group enquiry: what was asked, its status and the messages under it.
$stay_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('group-enquiry-view'),
    'folder'   => 'registration',
    'title'    => lang('Group Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your group enquiry'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Group Enquiries: the guest's own enquiries, and a card that starts
// another.
$stay_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-group-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Group Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Group Enquiries'), lang('Your group enquiries, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array($st_new_card('bi-people', lang('Another group?'),
                        lang('Start a new enquiry; your details are filled in for you.'), lang('Send a Group Enquiry'), 'group_enquiry')), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every group enquiry, newest first, with a search.
$stay_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-group-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Group Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Group Enquiries'), lang('Every group enquiry, newest first: send the offer, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_enquiries_list'))),
        )),
    )),
);

// The e-mails of the group enquiry.
$stay_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-group-enquiry'),
    'title' => lang('New Group Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$stay_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-group-enquiry-received'),
    'title' => lang('Group Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The guest's name.
$st_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The stay's dates: arrival, an arrow, departure.
$st_dates = function () use ($span, $bind, $st_sample_arrival, $st_sample_departure) {
    return array(
        $span($st_sample_arrival, '', lang('Arrival'), $bind('arrival_date')),
        $span(' → ', '', lang('Text')),
        $span($st_sample_departure, '', lang('Departure'), $bind('departure_date')),
    );
};
// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$st_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$st_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A record in a list: the title, the status, the details, linked to its
// page. The staff's row starts with the guest's name and adds the phone;
// the guest's own adds the messages. $items: array(icon, label, sample,
// field) each.
$st_row = function ($name, $title_sample, $title_label, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $st_person) {
    $nodes = array();
    if ($staff) $items[] = array('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
    else $items[] = array('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
    foreach ($items as $i) {
        $nodes[] = $el('span', 'd-inline-flex align-items-center gap-1', $i[1], array($icon($i[0]), $span($i[2], '', $i[1], $bind($i[3]))));
    }
    if ($staff) {
        $title = $el('span', 'fw-semibold', lang('Guest'), array_merge($st_person(), array(
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
$st_request_row = function ($staff = false) use ($st_row, $st_sample_kind, $st_sample_unit, $st_sample_arrival, $request_statuses) {
    return $st_row(lang('Stay Request'), $st_sample_kind, lang('Kind of stay'), 'stay_kind', $request_statuses[0], array(
        array('bi-calendar-range', lang('Arrival'), $st_sample_arrival, 'arrival_date'),
        array('bi-signpost', lang('Place'), $st_sample_unit, 'unit'),
        array('bi-people', lang('Adults'), '2', 'adults'),
    ), $staff);
};
$gr_enquiry_row = function ($staff = false) use ($st_row, $group_kinds, $group_sizes, $enquiry_statuses, $st_sample_arrival) {
    return $st_row(lang('Group Enquiry'), $group_kinds[0], lang('Kind of group'), 'group_kind', $enquiry_statuses[0], array(
        array('bi-calendar-range', lang('Arrival'), $st_sample_arrival, 'arrival_date'),
        array('bi-people', lang('Group size'), $group_sizes[1], 'group_size'),
        array('bi-send', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    ), $staff);
};

// The form detail settings of a widget: the e-mail pages read the record
// they are sent for (public), the record's page only the guest's own.
$st_detail_config = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the guest's own (the staff see them all), or the
// staff's with a search.
$st_list = function ($page_key, $form_key, $detail_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop) {
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
// notices, the toolbar, the card with the details, the guest's words and
// a note on the messages under it.
$st_record = function ($w) use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $span, $link, $attr, $bind, $show_when, $edit_button, $st_bound) {
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
                            // The single-use set password link, for the guest who sent the
                            // form while it is live; the sentence below covers a used or
                            // expired link.
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
                                        $para($w['eyebrow'], 'pcp-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $w['title_sample'], 'pcp-display h3 mb-0', $bind($w['title_field'])),
                                    )),
                                    $span($w['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $w['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $w['words_label'], array(
                            $para($w['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $st_bound($w['words_sample'], $w['words_field'])),
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
// No Reply, Share or follow box: the guest is e-mailed every answer and
// the park every message.
$st_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The lines of a stay request, for its e-mails.
$st_mail_request = function () use ($mail_box, $mail_line, $span, $bind, $st_dates, $st_sample_kind, $st_sample_unit, $st_sample_season, $outfit_choices, $pet_choices) {
    return $mail_box(array(
        $mail_line(lang('Kind of stay'), array($span($st_sample_kind, '', lang('Kind of stay'), $bind('stay_kind')))),
        $mail_line(lang('Place'), array($span($st_sample_unit, '', lang('Place'), $bind('unit')))),
        $mail_line(lang('Price season'), array($span($st_sample_season, '', lang('Price season'), $bind('season')))),
        $mail_line(lang('Stay'), $st_dates()),
        $mail_line(lang('Adults'), array($span('2', '', lang('Adults'), $bind('adults')))),
        $mail_line(lang('Children'), array($span('1', '', lang('Children'), $bind('children')))),
        $mail_line(lang('Pets'), array($span($pet_choices[1], '', lang('Pets'), $bind('pets')))),
        $mail_line(lang('Outfit'), array($span($outfit_choices[2], '', lang('Outfit'), $bind('outfit')))),
        $mail_line(lang('Outfit length (m)'), array($span('7', '', lang('Outfit length (m)'), $bind('outfit_length')))),
    ), lang('Stay Request'));
};
// The lines of a group enquiry, for its e-mails.
$gr_mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $st_dates, $group_kinds, $group_sizes, $kind_titles) {
    return $mail_box(array(
        $mail_line(lang('Kind of group'), array($span($group_kinds[0], '', lang('Kind of group'), $bind('group_kind')))),
        $mail_line(lang('Group size'), array($span($group_sizes[1], '', lang('Group size'), $bind('group_size')))),
        $mail_line(lang('Stay'), $st_dates()),
        $mail_line(lang('How the group sleeps'), array($span($kind_titles[0], '', lang('How the group sleeps'), $bind('stay_kind')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Group Enquiry'));
};
// The guest's lines of a staff e-mail; $words is the field of what the
// guest wrote and its sample.
$st_mail_guest = function ($words_label, $words_sample, $words_field) use ($mail_box, $mail_line, $span, $bind, $st_person) {
    return $mail_box(array(
        $mail_line(lang('Guest'), $st_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line($words_label, array($span($words_sample, '', $words_label, $bind($words_field)))),
    ), lang('Guest'));
};
// The reference code under a staff e-mail.
$st_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a guest who had none, in the reply e-mail.
$st_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #d4e4d0;padding-top:16px'), $show_when('has_new_account')));
};

$st_not_before_today = array('rules' => array('min_date' => 'today'));
$st_count = function ($id, $name, $min, $max, $value) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'),
        array($attr('min', (string)$min), $attr('max', (string)$max), $attr('step', '1'), $attr('value', (string)$value)));
};
// The guest's name, e-mail and phone, the same four fields on both forms.
$st_contact_fields = function ($prefix) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};
// The arrival and departure dates, never before today.
$st_date_fields = function ($prefix) use ($row, $col, $field, $input, $st_not_before_today) {
    return $row(array(
        $col(array($field($prefix . '-arrival', lang('Arrival'), $input($prefix . '-arrival', 'date', 'arrival_date', 'off', true, $st_not_before_today))), '6'),
        $col(array($field($prefix . '-departure', lang('Departure'), $input($prefix . '-departure', 'date', 'departure_date', 'off', true, $st_not_before_today))), '6'),
    ), '3');
};
// The end of a form: the consent with the privacy link, the CAPTCHA, the
// button and a line beside it.
$st_form_end = function ($consent_id, $consent_text, $button, $line) use ($el, $agree, $captcha, $submit, $para, $loop) {
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

$stay_widgets = array(

    // The stay request: the fields are the page's form. The kind, the place
    // and the season start with what a link sends (?kind=, ?unit=,
    // ?season=). The Status is the reception's (office use only): every
    // request starts as Request received.
    'request_form' => array(
        'page'   => 'book',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your stay')),
            $st_date_fields('cp-rq'),
            $row(array(
                $col(array($field('cp-rq-adults', lang('Adults'), $st_count('cp-rq-adults', 'adults', 1, 8, 2))), '6'),
                $col(array($field('cp-rq-children', lang('Children'), $st_count('cp-rq-children', 'children', 0, 8, 0), 'mb-3',
                    lang('Under twelve; tell us their ages in your notes.'))), '6'),
            ), '3'),
            $form_step(2, lang('Your place')),
            $row(array(
                $col(array($field('cp-rq-kind', lang('Kind of stay'), $select('cp-rq-kind', 'stay_kind', lang('Choose a kind of stay…'), $request_kind_choices, true, '', array('url_param' => 'kind')))), '6'),
                $col(array($field('cp-rq-season', lang('Price season'), $select('cp-rq-season', 'season', '', $st_season_choices, false, lang('Not sure yet'), array('url_param' => 'season')))), '6'),
            ), '3'),
            $field('cp-rq-unit', lang('Place'), $select('cp-rq-unit', 'unit', '', $request_unit_choices, false, $request_unit_choices[0], array('url_param' => 'unit')), 'mb-3',
                lang('A pitch or a cabin you liked, or any free place of the kind you chose.')),
            $row(array(
                $col(array($field('cp-rq-outfit', lang('Outfit'), $select('cp-rq-outfit', 'outfit', lang('Choose what you bring…'), $outfit_choices, true))), '6'),
                $col(array($field('cp-rq-length', lang('Outfit length (m)'), $input('cp-rq-length', 'number', 'outfit_length', 'off', false, array('kind' => 'number'),
                    array($attr('min', '0'), $attr('step', '0.5'))), 'mb-3', lang('A caravan or a motorhome with its drawbar, for the right pitch.'))), '6'),
            ), '3'),
            $field('cp-rq-pets', lang('Pets'), $select('cp-rq-pets', 'pets', '', $pet_choices, false, $pet_choices[0])),
            $field('cp-rq-notes', lang('Notes'), $textarea('cp-rq-notes', 'notes', 3, false), 'mb-3',
                lang('Shade or sun, near the lake or the play area, a late arrival, a birthday on site.')),
            $form_step(3, lang('How to reach you')),
            $st_contact_fields('cp-rq'),
            $field('cp-rq-status', lang('Status'), $select('cp-rq-status', 'status', '', $request_statuses, false, $request_statuses[0], array('office_use_only' => true))),
        ), $st_form_end('cp-rq-consent',
            lang('I agree that the campsite keeps these details to answer my request and prepare my stay, as its privacy notice explains.'),
            lang('Send the Request'), lang('The reception answers within a day. Nothing is paid now.')))),
    ),

    // One stay request: the request, its status and what the guest wrote;
    // the reception's answers (the offer among them) follow as comments.
    'request_detail' => $st_detail_config('request_view', 'book', $st_missing, 'submitter_only') + array(
        'tree' => $st_record(array(
            'not_found_title' => lang('Stay request not found'),
            'missing'         => $st_missing,
            'list_title'      => lang('My Stay Requests'),
            'list_key'        => 'my_requests',
            'sent'            => lang('Thank you, your request has reached us. The reception answers here within a day with an offer; this page shows the status, and you can write to us below.'),
            'card'            => lang('Request Card'),
            'eyebrow'         => lang('Stay Request'),
            'title_sample'    => $st_sample_kind,
            'title_field'     => 'stay_kind',
            'status'          => $request_statuses[0],
            'details'         => array_merge(
                $st_detail(lang('Stay'), '', lang('Stay'), $st_dates(), true),
                $st_detail(lang('Place'), '', lang('Place'), $st_bound($st_sample_unit, 'unit')),
                $st_detail(lang('Price season'), '', lang('Price season'), $st_bound($st_sample_season, 'season')),
                $st_detail(lang('Adults'), '', lang('Adults'), $st_bound('2', 'adults')),
                $st_detail(lang('Children'), '', lang('Children'), $st_bound('1', 'children')),
                $st_detail(lang('Pets'), '', lang('Pets'), $st_bound($pet_choices[1], 'pets')),
                $st_detail(lang('Outfit'), '', lang('Outfit'), $st_bound($outfit_choices[2], 'outfit')),
                $st_detail(lang('Outfit length (m)'), 'pg-hide-if-empty', lang('Outfit length (m)'), $st_bound('7', 'outfit_length')),
                $st_detail(lang('Guest'), '', lang('Guest'), $st_person(), true),
                $st_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $st_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('Notes'),
            'words_sample'    => $st_sample_notes,
            'words_field'     => 'notes',
            'note'            => lang('Other dates, a question about the offer or something to add? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'request_messages' => $st_messages('request_view',
        lang('No messages yet. The reception\'s offer comes here; you can write to us about your stay too.')),

    'my_requests_list' => $st_list('my_requests', 'book', 'request_view', $st_request_row(),
        lang('No stay requests yet. Tell us your dates and how you camp.')),

    'staff_requests_list' => $st_list('staff_requests', 'book', 'request_view', $st_request_row(true),
        lang('No stay requests yet.'),
        array('first_name', 'last_name', 'email', 'stay_kind', 'unit', 'season', 'status'),
        lang('Search by guest, e-mail, kind, place, season or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_request_admin' => $st_detail_config('email_request_new', 'book', lang('The stay request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new stay request')),
            $mail_para(lang('It came from the stay request form. Answer under the request on its page with an offer and the place you hold, and set the status; what you write there reaches the guest by e-mail.')),
            $st_mail_request(),
            $st_mail_guest(lang('Notes'), $st_sample_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:request_view}}?r=^^reference_code^^'),
            $st_mail_reference(),
        )),
    ),
    'email_request_client' => $st_detail_config('email_request_received', 'book', lang('The stay request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your stay request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The reception looks at your dates and answers within a day with an offer and the place held for you, on the request\'s page and by e-mail. Nothing is paid now.')),
            $st_mail_request(),
            $mail_para(lang('Other dates or a question? Write to us on the request\'s page; everything about your stay stays there.')),
            $mail_button(lang('Open my request'), '{{page:request_view}}?r=^^reference_code^^'),
            $st_mail_new_account(),
        )),
    ),

    // The group enquiry: the fields are the page's form. The kind of group
    // starts with the card that sent the visitor (?kind=). The Status is
    // the park's (office use only): every enquiry starts as Enquiry
    // received.
    'enquiry_form' => array(
        'page'   => 'group_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your group')),
            $row(array(
                $col(array($field('cp-gr-kind', lang('Kind of group'), $select('cp-gr-kind', 'group_kind', lang('Choose a kind of group…'), $group_kinds, true, '', array('url_param' => 'kind')))), '6'),
                $col(array($field('cp-gr-size', lang('Group size'), $select('cp-gr-size', 'group_size', lang('Choose a group size…'), $group_sizes, true))), '6'),
            ), '3'),
            $st_date_fields('cp-gr'),
            $field('cp-gr-sleep', lang('How the group sleeps'), $select('cp-gr-sleep', 'stay_kind', '', $request_kind_choices, false, lang('Not sure yet'))),
            $field('cp-gr-details', lang('About the group'), $textarea('cp-gr-details', 'details', 4, false), 'mb-3',
                lang('Ages, leaders, activities you would like, meals or anything we should know.')),
            $field('cp-gr-documents', lang('Programme or list'), $file('cp-gr-documents', 'documents', 'private'), 'mb-3',
                lang('A programme, a list of tents or outfits (PDF, Word or a picture). More can follow under your enquiry.')),
            $form_step(2, lang('How to reach you')),
            $radios('cp-gr-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $st_contact_fields('cp-gr'),
            $field('cp-gr-organisation', lang('School, club or organisation'), $input('cp-gr-organisation', 'text', 'organisation', 'organization', false, array('contact_field' => 'company'))),
            $field('cp-gr-status', lang('Status'), $select('cp-gr-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
        ), $st_form_end('cp-gr-consent',
            lang('I agree that the campsite keeps these details to plan the group\'s stay and answer me, as its privacy notice explains.'),
            lang('Send the Enquiry'), lang('We answer within two working days.')))),
    ),

    // One group enquiry: what was asked, its status and what the group
    // wrote; the offer and the messages follow as comments.
    'enquiry_detail' => $st_detail_config('enquiry_view', 'group_enquiry', $gr_missing, 'submitter_only') + array(
        'tree' => $st_record(array(
            'not_found_title' => lang('Group enquiry not found'),
            'missing'         => $gr_missing,
            'list_title'      => lang('My Group Enquiries'),
            'list_key'        => 'my_enquiries',
            'sent'            => lang('Thank you, your group enquiry has reached us. We answer within two working days with an offer; this page shows the status, and you can write to us below.'),
            'card'            => lang('Enquiry Card'),
            'eyebrow'         => lang('Group Enquiry'),
            'title_sample'    => $group_kinds[0],
            'title_field'     => 'group_kind',
            'status'          => $enquiry_statuses[0],
            'details'         => array_merge(
                $st_detail(lang('Stay'), '', lang('Stay'), $st_dates(), true),
                $st_detail(lang('Group size'), '', lang('Group size'), $st_bound($group_sizes[1], 'group_size')),
                $st_detail(lang('How the group sleeps'), '', lang('How the group sleeps'), $st_bound($kind_titles[0], 'stay_kind')),
                $st_detail(lang('Best way to reach you'), '', lang('Contact by'), $st_bound(lang('E-mail'), 'contact_method')),
                $st_detail(lang('Guest'), '', lang('Guest'), $st_person(), true),
                $st_detail(lang('School, club or organisation'), 'pg-hide-if-empty', lang('Organisation'), $st_bound(lang('Pine Valley Scouts'), 'organisation')),
                $st_detail(lang('Programme or list'), 'pg-hide-if-empty text-break', lang('Documents'), array(
                    $link('programme.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                ), true),
                $st_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $st_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('About the group'),
            'words_sample'    => $gr_sample_note,
            'words_field'     => 'details',
            'note'            => lang('Other dates, more people or a question? Write to us below; the offer comes here too, and every answer by e-mail.'),
        )),
    ),

    'enquiry_messages' => $st_messages('enquiry_view',
        lang('No messages yet. Our offer comes here; you can write to us about your group too.')),

    'my_enquiries_list' => $st_list('my_enquiries', 'group_enquiry', 'enquiry_view', $gr_enquiry_row(),
        lang('No group enquiries yet. Tell us about your group and we answer within two working days.')),

    'staff_enquiries_list' => $st_list('staff_enquiries', 'group_enquiry', 'enquiry_view', $gr_enquiry_row(true),
        lang('No group enquiries yet.'),
        array('first_name', 'last_name', 'organisation', 'group_kind', 'group_size', 'status'),
        lang('Search by guest, organisation, kind of group, size or status...')),

    'email_enquiry_admin' => $st_detail_config('email_enquiry_new', 'group_enquiry', lang('The group enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new group enquiry')),
            $mail_para(lang('It came from the group enquiry form. Check the dates and the places, send the offer under the enquiry and set its status; what you write there reaches the guest by e-mail.')),
            $gr_mail_enquiry(),
            $st_mail_guest(lang('About the group'), $gr_sample_note, 'details'),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $st_mail_reference(),
        )),
    ),
    'email_enquiry_client' => $st_detail_config('email_enquiry_received', 'group_enquiry', lang('The group enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your group enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us for your group. We answer within two working days with an offer and hold the dates for you while you decide.')),
            $gr_mail_enquiry(),
            $mail_para(lang('A change or a question? Write to us on the enquiry\'s page; everything about your group stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $st_mail_new_account(),
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

return array('pages' => $stay_pages, 'widgets' => $stay_widgets);
