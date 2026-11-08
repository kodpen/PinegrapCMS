<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its stay and group enquiries: the enquiry
 * page, the enquiry's own page with its messages, My Enquiries, the
 * staff's list and the two e-mails of an enquiry. Included after
 * lessons.php in the same scope (it reuses that file's request pieces);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A stay is an enquiry, never a booking: a record of the Stay Enquiries
 * form. Guest services answer each enquiry with an offer and set its
 * office-use-only Status; the answers are the page's comments, mailed to
 * the guest and to the resort. A guest without an account gets one
 * (auto-registration). The stay and the kind of group start with what a
 * lodging card or a group link sends (?stay=, ?group=, each in its list's
 * own words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$enquiry_pages = array();

// What the group would like beside the stay; the offer puts it together.
$extra_choices = array(lang('Lift passes and lessons'), lang('Lift passes only'), lang('Lessons only'), lang('Only the stay'));

// The sample enquiry the canvas shows.
$sample_stay = $lodging['chalets']['title'];
$sample_group = $group_kinds[2];
$sample_nights = $night_choices[3];
$sample_guests = '12';
$sample_extras = $extra_choices[0];
$sample_enquiry_notes = lang('A ski club of twelve, eight adults and four teenagers, half of them beginners. Two chalets side by side would be ideal.');

// ── Pages ───────────────────────────────────────────────────────────────

// The enquiry. What is sent opens the enquiry's own page.
$enquiry_pages[] = array(
    'key'              => 'group_enquiry',
    'name'             => lang('stay-and-group-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Stays and Groups'),
    'meta_description' => lang('Ask about a stay at the resort for your family, club, school or company: guest services send one offer with the stay, passes and lessons.'),
    'form'             => array(
        'form_name'            => lang('Stay Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached guest services. They send you an offer within two days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New stay enquiry: ^^stay^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your stay enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $request_page(lang('Stays and Groups'),
        lang('Tell us where you would like to stay, when you arrive and who is coming. Guest services send one offer with the stay, the passes and the lessons; nothing is paid now.'),
        'enquiry_form',
        array_merge(array(
            $el('div', 'p-4 rounded-4 border mb-4', lang('Lesson Request'), array(
                $heading('h2', lang('Only a lesson?'), 'psk-display h5 mb-2'),
                $para(lang('The ski school answers lesson requests on their own, within a day.'), 'small text-body-secondary mb-3'),
                $link(lang('Request a Lesson'), '{{page:lesson_request}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ), $request_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('Guest services send you an offer there within two days: the stay, the passes and the lessons in one price.'),
            lang('The offer says how to confirm it and when the deposit is due.'),
            lang('Plans change? Write to us on the enquiry\'s page.'),
        ), lang('See the places to stay'), '{{page:lodging}}'))),
);

// One enquiry: the enquiry, its status and the messages.
$enquiry_pages[] = $request_view('enquiry_view', lang('stay-enquiry'), lang('Stay Enquiry'),
    'enquiry_detail', 'enquiry_messages', lang('There is a new message about your stay enquiry'));

// My Enquiries: the guest's own enquiries (for the staff, everybody's).
$enquiry_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your stay and group enquiries, their status and the offers of guest services, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-house-heart', 'text-primary'),
                                $span(lang('Another stay?'), '', lang('Text')),
                            )),
                            $para(lang('Choose a place to stay; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the places to stay'), '{{page:lodging}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$enquiry_pages[] = $staff_list_page('staff_enquiries', lang('all-stay-enquiries'), lang('Stay Enquiries'),
    lang('Every stay and group enquiry, newest first: send the offer, set the status and write to the guest under each.'),
    'staff_enquiries_list');

// The e-mails: what the resort receives and the reply to the guest.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-stay-enquiry'),
    'title' => lang('New Stay Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-stay-enquiry-received'),
    'title' => lang('Stay Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The enquiry's stay, group and dates, for its e-mails.
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_stay, $sample_group, $sample_nights, $sample_guests, $sample_extras) {
    return $mail_box(array(
        $mail_line(lang('Stay'), array($span($sample_stay, '', lang('Stay'), $bind('stay')))),
        $mail_line(lang('Group'), array($span($sample_group, '', lang('Group'), $bind('group_kind')))),
        $mail_line(lang('Arrival'), array($span(lang('Arrival'), '', lang('Arrival'), $bind('arrival')))),
        $mail_line(lang('Nights'), array($span($sample_nights, '', lang('Nights'), $bind('nights')))),
        $mail_line(lang('Guests'), array($span($sample_guests, '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Also wanted'), array($span($sample_extras, '', lang('Also wanted'), $bind('extras')))),
    ), lang('Stay Enquiry'));
};
$enquiry_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_stay, $sample_group, $sample_guests, $enquiry_statuses) {
    return $request_row($staff, $sample_stay, 'stay', lang('Stay'), $enquiry_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Group'), array(
            $icon('bi-people'), $span($sample_group, '', lang('Group'), $bind('group_kind')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Arrival'), array(
            $icon('bi-calendar-event'), $span(lang('Arrival'), '', lang('Arrival'), $bind('arrival')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array(
            $icon('bi-person'), $span($sample_guests, '', lang('Guests'), $bind('guests')),
        )),
    ));
};
$enquiry_missing = lang('This stay enquiry could not be found, or it is not yours to see.');

$enquiry_widgets = array(

    // The enquiry: the fields are the page's form. The stay and the kind of
    // group start with what a lodging card or a group link sends (?stay=,
    // ?group=). The Status is guest services' (office use only): every
    // enquiry starts as Enquiry received. A list of names or a programme
    // may come with it.
    'enquiry_form' => array(
        'page'   => 'group_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The stay')),
            $row(array(
                $col(array($field('skr-enq-stay', lang('Where to stay'), $select('skr-enq-stay', 'stay', lang('Choose…'), $stay_choices, true, '', array('url_param' => 'stay')))), '6'),
                $col(array($field('skr-enq-group', lang('Who is coming'), $select('skr-enq-group', 'group_kind', lang('Choose…'), $group_kinds, true, '', array('url_param' => 'group')))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('skr-enq-arrival', lang('Arrival'), $input('skr-enq-arrival', 'date', 'arrival', 'off', true, $not_before_today))), '4'),
                $col(array($field('skr-enq-nights', lang('Nights'), $select('skr-enq-nights', 'nights', '', $night_choices, true, $night_choices[0]))), '4'),
                $col(array($field('skr-enq-guests', lang('Guests'), $input('skr-enq-guests', 'number', 'guests', 'off', true, array(), array($attr('min', '1'), $attr('max', '200'))),
                    'mb-3', lang('Children included.'))), '4'),
            ), '3'),
            $field('skr-enq-extras', lang('Also wanted'), $select('skr-enq-extras', 'extras', '', $extra_choices, true, $extra_choices[0])),
            $field('skr-enq-notes', lang('Notes'), $textarea('skr-enq-notes', 'notes', 3, false), 'mb-3',
                lang('The guests\' ages and levels, rooms you need, a programme or a budget.')),
            $field('skr-enq-list', lang('List of names or programme'), $file('skr-enq-list', 'attachment', 'private'), 'mb-3',
                lang('Optional: a PDF, an office file or a photo.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('skr-enq'),
            $radios('skr-enq-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('skr-enq-status', lang('Status'), $select('skr-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('skr-enq-consent', 'consent', lang('I agree that the resort keeps these details to prepare the offer with me.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Guest services send an offer within two days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry; the offers of guest services follow as comments.
    'enquiry_detail' => $detail_widget('enquiry_view', 'group_enquiry', $enquiry_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Enquiries'), 'my_enquiries', $enquiry_missing, lang('Stay enquiry not found'),
            lang('Stay Enquiry'), $sample_stay, 'stay', $enquiry_statuses[0],
            lang('Thank you, your enquiry has reached guest services. They send you an offer here within two days; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Group'), '', lang('Group'), $bound($sample_group, 'group_kind')),
                $detail(lang('Arrival'), '', lang('Arrival'), $bound(lang('Arrival'), 'arrival')),
                $detail(lang('Nights'), '', lang('Nights'), $bound($sample_nights, 'nights')),
                $detail(lang('Guests'), '', lang('Guests'), $bound($sample_guests, 'guests')),
                $detail(lang('Also wanted'), '', lang('Also wanted'), $bound($sample_extras, 'extras')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_enquiry_notes,
            lang('Another date, more guests or a question about the offer? Write to us below; we answer here and by e-mail.')),
    ),

    'enquiry_messages' => $request_messages('enquiry_view',
        lang('No messages yet. The offer of guest services comes here; you can write to us about the stay too.')),

    'my_enquiries_list' => $request_list('my_enquiries', 'group_enquiry', 'enquiry_view', $enquiry_row, array(), '',
        lang('No stay enquiries yet. Choose a place to stay and ask us about it.')),

    'staff_enquiries_list' => $request_list('staff_enquiries', 'group_enquiry', 'enquiry_view', $enquiry_row,
        array('first_name', 'last_name', 'email', 'stay', 'group_kind', 'status'),
        lang('Search by name, e-mail, stay, group or status...'), lang('No stay enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', 'group_enquiry', lang('The stay enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new stay enquiry')),
            $mail_para(lang('It came from the stay and group enquiry form. Send the offer under the enquiry on its page and set the status; what you write there reaches the guest by e-mail.')),
            $mail_enquiry(),
            $mail_sender($sample_enquiry_notes),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', 'group_enquiry', lang('The stay enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your stay enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Guest services send you an offer within two days, on the enquiry\'s page and by e-mail.')),
            $mail_enquiry(),
            $mail_para(lang('Plans change? Write to us on the enquiry\'s page; everything about the stay stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
