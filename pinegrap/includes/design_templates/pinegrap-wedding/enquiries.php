<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue", its enquiries: the wedding
 * enquiry, the enquiry's own page with its messages, My Enquiries and the
 * staff's list; the venue visit booked at a free time; the privacy page the
 * consent boxes link, the e-mails of the enquiry form and the new message
 * e-mail every thread of the site sends, and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a request, never a booking of the date: a record of the
 * Wedding Enquiries form. A couple without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once. Its Status is an office-use-only field the planner changes in the
 * control panel; the consultation, the proposal and the date held are
 * written under it as messages (the page's comments), mailed to the couple
 * and to the planner. The template keeps no calendar of the venue's dates.
 *
 * The package, the service and the space start with what a link sends
 * (?package=, ?service=, ?space=: their titles, as parts.php writes them).
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

// The sample enquiry the canvas shows. Its date is words, never a date: a
// fixed day would read as a past season a year later.
$sample_package = $packages['classic']['title'];
$sample_service = $services['full']['title'];
$sample_space = $spaces['garden']['title'];
$sample_season = $seasons['summer']['title'];
$sample_date = lang('Wedding date');
$sample_budget = $budget_choices[1];
$sample_wishes = lang('We would love the ceremony under the olive trees at sunset and a long dinner with our families; about half of our guests travel from abroad.');
$deposit_words = $money($deposit);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the enquiry page: what happens next, then good to
// know and the way to see the venue first.
$enquiry_aside = function () use ($el, $heading, $para, $link, $check_list, $deposit_words, $planner) {
    $steps = array(
        lang('Your enquiry opens its own page, and an account for you if you have none.'),
        lang('We answer there within two days and suggest a time for a first consultation.'),
        lang('After the consultation you receive a proposal with the price, written under your enquiry.'),
        sprintf(lang('Your date is held once you agree to the proposal and pay the date-hold deposit of %s.'), $deposit_words),
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
        $el('div', 'p-4 rounded-4 border mb-4', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
            $check_list(array(
                $planner['promise'],
                lang('Nothing is paid for an enquiry or a consultation.'),
                lang('Not sure of the date yet? Choose a season and we tell you what is free.'),
            ), 'list-unstyled small mb-3'),
            $link(lang('Read the planning timeline'), '{{page:timeline}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Visit First'), array(
            $heading('h2', lang('Rather see the venue first?'), 'h5 fw-semibold mb-2'),
            $para(lang('Choose a free time for a venue visit; we walk you through the spaces and answer your questions.'), 'small text-body-secondary mb-3'),
            $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The wedding enquiry. What is sent opens the enquiry's own page.
$enquiry_pages[] = array(
    'key'              => 'enquiry',
    'name'             => lang('wedding-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Wedding Enquiry'),
    'meta_description' => lang('Tell us about your wedding: we answer within two days with a time for a first consultation. Nothing is paid now.'),
    'form'             => array(
        'form_name'            => lang('Wedding Enquiries'),
        'confirmation_message' => lang('Thank you, your wedding enquiry has reached us. We answer within two days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New wedding enquiry: ^^first_name^^ and ^^partner_name^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your wedding enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Wedding Enquiry'),
            lang('Tell us about the day you imagine. We answer within two days with a time for a first consultation; nothing is paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('enquiry_form'))),
                        )),
                    ), '', '8'),
                    $col($enquiry_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One enquiry: the enquiry, its status and the messages under it.
$enquiry_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('enquiry'),
    'folder'   => 'registration',
    'title'    => lang('Wedding Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your wedding enquiry'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Enquiries: the couple's own enquiries (for the staff, everybody's).
$enquiry_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your wedding enquiries, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-envelope-heart', 'text-primary'),
                                $span(lang('Another date or package?'), '', lang('Text')),
                            )),
                            $para(lang('Send another enquiry; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Enquire'), '{{page:enquiry}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Visit First'), array(
                            $para(lang('See the spaces before you decide: choose a free time for a venue visit.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$enquiry_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-wedding-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Wedding Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Wedding Enquiries'), lang('Every wedding enquiry, newest first: suggest a consultation, send the proposal, set the status and write to the couple under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_enquiries_list'))),
        )),
    )),
);

// The venue visit or a first consultation at a free time: the booking
// widget's own layout beside the way to send an enquiry first and the
// planner's hours.
$enquiry_pages[] = array(
    'key'              => 'book_visit',
    'name'             => lang('venue-visit'),
    'folder'           => 'public',
    'title'            => lang('Book a Venue Visit'),
    'meta_description' => lang('Choose a free time to visit the venue or meet us for a first consultation, and confirm in a minute.'),
    'tree'             => $page(array(
        $page_header(lang('Book a Venue Visit'), lang('Walk through the gardens and the halls with us, or meet us for a first consultation. Choose one of the free times; it is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('visit_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Ask First'), array(
                            $heading('h2', lang('Rather ask first?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Tell us about your wedding in an enquiry and we answer within two days with a time that suits you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Send an Enquiry'), '{{page:enquiry}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('The Venue'), array(
                            $heading('h2', lang('The Venue'), 'h5 fw-semibold mb-3'),
                            $check_list(array($planner['address'], $planner['hours'])),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('wd-book-visit', lang('venue visit, consultation, free time, appointment, see the venue'))),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$enquiry_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the planner keeps about you when you enquire about your wedding or book a visit, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the planner keeps about you when you enquire about your wedding or book a visit, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you send a wedding enquiry, book a venue visit, buy a gift card, join the newsletter or open an account, we keep your names, your contact details, your wedding date and what you tell us about your wedding, to answer you, prepare your proposal and plan your day.'), 'mb-3'),
                $para(lang('We pass to the suppliers of your wedding only what they need to prepare it. We keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('wd-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the planner receives and the reply to the couple, and
// the new message e-mail.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-wedding-enquiry'),
    'title' => lang('New Wedding Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-wedding-enquiry-received'),
    'title' => lang('Wedding Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));
// A new message or reply: sent to the couple of an enquiry, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your wedding enquiry, a conversation or a Wedding Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The couple's names: the one who wrote, then the partner.
$couple_name = function () use ($span, $bind) {
    return array(
        $span(lang('Selin'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Aydın'), '', lang('Last Name'), $bind('last_name')),
        $span(' & ', '', lang('Text')),
        $span(lang('Emre'), '', lang('Partner'), $bind('partner_name')),
    );
};

// An enquiry in a list: the package, the status, the date or season, the
// guests and the space, linked to its page. The staff's row starts with the
// couple's names and adds the phone; the couple's own adds the messages.
$enquiry_row = function ($staff = false) use ($el, $span, $icon, $bind, $couple_name, $sample_package, $sample_date, $sample_season, $sample_space, $enquiry_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-heart', lang('Date'), array(
            $span($sample_date, 'me-1', lang('Wedding Date'), $bind('wedding_date')),
            $span($sample_season, '', lang('Season'), $bind('season')),
        )),
        $item('bi-people', lang('Guests'), array($span('120', '', lang('Guests'), $bind('guests')))),
        $item('bi-geo-alt', lang('Space'), array($span($sample_space, '', lang('Space'), $bind('space')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Couple'), array_merge($couple_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_package, 'text-body-secondary fw-normal', lang('Package'), $bind('package')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_package, 'fw-semibold', lang('Package'), $bind('package'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Wedding Enquiry'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
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
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_package, $sample_service, $sample_space, $sample_date, $sample_season, $sample_budget) {
    return $mail_box(array(
        $mail_line(lang('Package'), array($span($sample_package, '', lang('Package'), $bind('package')))),
        $mail_line(lang('Planning'), array($span($sample_service, '', lang('Service'), $bind('service')))),
        $mail_line(lang('Space'), array($span($sample_space, '', lang('Space'), $bind('space')))),
        $mail_line(lang('Wedding Date'), array($span($sample_date, '', lang('Wedding Date'), $bind('wedding_date')))),
        $mail_line(lang('Season'), array($span($sample_season, '', lang('Season'), $bind('season')))),
        $mail_line(lang('Guests'), array($span('120', '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Budget'), array($span($sample_budget, '', lang('Budget'), $bind('budget')))),
    ), lang('Wedding Enquiry'));
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
    ), array_merge($mail_style('border-top:1px solid #dfe7e0;padding-top:16px'), $show_when('has_new_account')));
};
// The couple's lines of the staff e-mail.
$mail_couple = function () use ($mail_box, $mail_line, $span, $bind, $couple_name, $sample_wishes) {
    return $mail_box(array(
        $mail_line(lang('Couple'), $couple_name()),
        $mail_line(lang('Email'), array($span('selin@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Best way to reach you'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line(lang('Your wedding'), array($span($sample_wishes, '', lang('Your wedding'), $bind('wishes')))),
    ), lang('Couple'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the enquiry page's
// widget (the couple's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:enquiry}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of enquiries: the couple's own (the staff see them all), or the
// staff's with a search.
$enquiry_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $enquiry_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:enquiry}}',
        'detail_page_id'      => '{{tab:enquiry_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'partner_name', 'email', 'package', 'space', 'season', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by name, e-mail, package, space, season or status...'),
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

$enquiry_missing = lang('This wedding enquiry could not be found, or it is not yours to see.');

$enquiry_widgets = array(

    // The enquiry: the fields are the page's form. The package, the service
    // and the space start with what a link sends (?package=, ?service=,
    // ?space=). The Status is the planner's (office use only): every
    // enquiry starts as Enquiry received.
    'enquiry_form' => array(
        'page'   => 'enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your wedding')),
            $row(array(
                $col(array($field('wd-enq-package', lang('Package'), $select('wd-enq-package', 'package', '', $package_choices, false, end($package_choices), array('url_param' => 'package')))), '6'),
                $col(array($field('wd-enq-service', lang('Planning'), $select('wd-enq-service', 'service', '', $service_choices, false, end($service_choices), array('url_param' => 'service')))), '6'),
            ), '3'),
            $field('wd-enq-space', lang('Space'), $select('wd-enq-space', 'space', '', $space_choices, false, end($space_choices), array('url_param' => 'space')), 'mb-3',
                lang('Where you would like the ceremony or the dinner; we suggest the rest at the consultation.')),
            $row(array(
                $col(array($field('wd-enq-date', lang('Wedding Date'), $input('wd-enq-date', 'date', 'wedding_date', 'off', false, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('Leave it empty if the date is open.'))), '6'),
                $col(array($field('wd-enq-season', lang('Season'), $select('wd-enq-season', 'season', '', $season_choices, false, end($season_choices)))), '6'),
                $col(array($field('wd-enq-guests', lang('Guests'), $input('wd-enq-guests', 'number', 'guests', 'off', true, array(),
                    array($attr('min', '10'), $attr('max', '300'), $attr('step', '1'), $attr('value', '100'))))), '6'),
                $col(array($field('wd-enq-budget', lang('Budget'), $select('wd-enq-budget', 'budget', '', $budget_choices, false, end($budget_choices)))), '6'),
            ), '3'),
            $radios('wd-enq-ceremony', 'ceremony', lang('Ceremony'), $ceremony_choices, $ceremony_choices[0][0]),
            $form_step(2, lang('Tell us about it')),
            $field('wd-enq-wishes', lang('Your wedding'), $textarea('wd-enq-wishes', 'wishes', 4, false), 'mb-3',
                lang('The feeling you want, the colours, the music, guests travelling from far, anything we should know.')),
            $field('wd-enq-documents', lang('Mood board or guest list'), $file('wd-enq-documents', 'documents', 'private'), 'mb-3',
                lang('Optional: a PDF, a document or a picture.')),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('wd-enq-first-name', lang('First Name'), $input('wd-enq-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('wd-enq-last-name', lang('Last Name'), $input('wd-enq-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('wd-enq-partner', lang('Your partner\'s name'), $input('wd-enq-partner', 'text', 'partner_name', 'off', true))), '6'),
                $col(array($field('wd-enq-email', lang('Email'), $input('wd-enq-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('wd-enq-phone', lang('Phone'), $input('wd-enq-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('wd-enq-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $field('wd-enq-status', lang('Status'), $select('wd-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('wd-enq-consent', 'consent', lang('I agree that the planner keeps these details to answer my enquiry and plan our wedding, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within two days. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry: the enquiry, its status and what the couple wrote; the
    // planner's answers (the consultation, the proposal) follow as
    // comments.
    'enquiry_detail' => $detail_widget('enquiry_view', $enquiry_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-envelope-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Wedding enquiry not found'), 'h3 fw-semibold mt-3'),
                            $para($enquiry_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your enquiry has reached us. We answer here within two days with a time for a first consultation; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                ), array('href' => '{{page:my_enquiries}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Enquiry Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Wedding Enquiry'), 'pwd-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $el('h1', 'pwd-display h3 mb-0', lang('Couple'), $couple_name()),
                                        )),
                                        $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Package'), '', lang('Package'), $bound($sample_package, 'package')),
                                        $detail(lang('Planning'), '', lang('Service'), $bound($sample_service, 'service')),
                                        $detail(lang('Space'), '', lang('Space'), $bound($sample_space, 'space')),
                                        $detail(lang('Wedding Date'), '', lang('Wedding Date'), $bound($sample_date, 'wedding_date')),
                                        $detail(lang('Season'), '', lang('Season'), $bound($sample_season, 'season')),
                                        $detail(lang('Guests'), '', lang('Guests'), $bound('120', 'guests')),
                                        $detail(lang('Budget'), '', lang('Budget'), $bound($sample_budget, 'budget')),
                                        $detail(lang('Ceremony'), '', lang('Ceremony'), $bound($ceremony_choices[0][1], 'ceremony')),
                                        $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('E-mail'), 'contact_method')),
                                        $detail(lang('Mood board or guest list'), 'text-break', lang('Documents'), array(
                                            $link('mood-board.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Your wedding'), array(
                                $para(lang('Your wedding'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_wishes, 'wishes')),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another date, a question about the proposal or something to add? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                                $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'fw-semibold link-primary text-decoration-none'),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an enquiry: the page's own Comments widget, shown
    // only to whoever may read the enquiry above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the couple is e-mailed every
    // answer and the planner every message.
    'enquiry_messages' => array(
        'page'   => 'enquiry_view',
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
            'empty'     => lang('No messages yet. Our answer comes here; you can write to us about your wedding too.'),
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

    'my_enquiries_list' => $enquiry_list('my_enquiries', false,
        lang('No wedding enquiries yet. Tell us about your wedding, and we answer within two days.')),

    'staff_enquiries_list' => $enquiry_list('staff_enquiries', true, lang('No wedding enquiries yet.')),

    // The booking widget with the editor's starter layout; it shows its
    // no-services text until Settings › Bookings has a service (a venue
    // visit, a first consultation).
    'visit_slots' => array(
        'page'   => 'book_visit',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', lang('This wedding enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new wedding enquiry')),
            $mail_para(lang('It came from the enquiry form. Answer under the enquiry on its page with a time for a first consultation, and set the status; what you write there reaches the couple by e-mail.')),
            $mail_enquiry(),
            $mail_couple(),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', lang('This wedding enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your wedding enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We read every enquiry ourselves and answer within two days with a time for a first consultation, on the enquiry\'s page and by e-mail. Nothing is paid now.')),
            $mail_enquiry(),
            $mail_para(lang('Something to add, or rather see the venue first? Write to us on the enquiry\'s page, or book a venue visit at a free time.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
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

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
