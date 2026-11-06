<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", its volunteers: the volunteer page (how one
 * can help and the offer form), a volunteer's own page with its status and
 * messages, My Volunteering, the staff's list of every offer, the privacy
 * notice the consent boxes link to, and the e-mails: the new offer to the
 * staff, the reply to the volunteer and the new message e-mail every
 * record page of the template sends. Included after parts.php, mail.php and
 * forms.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An offer is a record of the Volunteers form, built with the form-record
 * builders of forms.php as the pledge is: the Status is the staff's (office
 * use only), the messages under it are the record page's comments, and a
 * volunteer without an account gets one. The cause list is the causes'
 * titles, so a cause page's Volunteer link (?cause=<title>) chooses it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$volunteer_pages = array();

// The ways to help, the causes, the time one can give and the stages of an
// offer. Each list is the answers of its pick list, so the staff's search
// and the record page read the same words.
$volunteer_roles = array(
    lang('In the field'),
    lang('At events and collections'),
    lang('In the office'),
    lang('With my profession'),
    lang('Online, from home'),
);
$volunteer_causes = array(lang('Wherever I am needed'));
foreach ($causes as $c) $volunteer_causes[] = $c['title'];
$volunteer_times = array(
    array(lang('A few hours a month'), lang('A few hours a month')),
    array(lang('Weekends'), lang('Weekends')),
    array(lang('A few weeks a year'), lang('A few weeks a year')),
);
$volunteer_statuses = array(lang('Received'), lang('We called you'), lang('Training'), lang('Active'), lang('Closed'));
$sample_volunteer = lang('I am a nurse and speak French; I could join a health trip for two weeks in the summer.');
$volunteer_missing = lang('This offer could not be found, or it is not yours to see.');

// The ways to help, as cards on the volunteer page: icon, title, text.
$volunteer_ways = array(
    array('bi-geo-alt', lang('In the field'), lang('Join a trip to build, teach or care, with a field team that has done it for years.')),
    array('bi-calendar-event', lang('At events and collections'), lang('Run a stand, welcome guests or count a collection near you.')),
    array('bi-briefcase', lang('With my profession'), lang('A doctor, an engineer, a teacher or a translator: your skill goes far.')),
);
$volunteer_way_cards = array();
foreach ($volunteer_ways as $w) {
    $volunteer_way_cards[] = $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body shadow-sm', lang('Way to Help'), array(
            $icon($w[0], 'fs-3 text-primary'),
            $heading('h3', $w[1], 'h5 fw-semibold mt-3 mb-2'),
            $para($w[2], 'small text-body-secondary mb-0'),
        )),
    ), '', '4');
}

// The volunteer page: the ways to help, then the offer form beside what
// happens next.
$volunteer_pages[] = array(
    'key'              => 'volunteer',
    'name'             => lang('volunteer'),
    'folder'           => 'public',
    'title'            => lang('Volunteer'),
    'meta_description' => lang('Give your time in the field, at events, in the office or with your profession: tell us how you would like to help.'),
    'form'             => array(
        'form_name'            => lang('Volunteers'),
        'confirmation_message' => lang('Thank you for offering your time. We call you within a week; you can follow your offer from your account.'),
        'confirmation_page_id' => '{{tab:volunteer_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New volunteer: ^^help_with^^'),
        'notify_page_id'       => '{{tab:email_volunteer_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your offer to volunteer'),
        'confirm_page_id'      => '{{tab:email_volunteer_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Volunteer'), lang('Give your time where it helps most. Tell us how you would like to help; we answer everyone within a week.')),
        $el('section', 'py-5 bg-body-tertiary', lang('Ways to Help'), array(
            $container(array(
                $section_header(lang('Ways to help'), lang('Where your time goes')),
                $row($volunteer_way_cards, '4'),
            )),
        ), $searchable('ways-to-help', lang('volunteer, help, field trip, events, skills'))),
        $el('section', 'py-5', lang('Offer'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Offer your time'), 'h4 fw-semibold mb-4'),
                                $widget('volunteer_form'),
                            )),
                        )),
                    ), '', '8'),
                    $col($request_aside(array(
                        lang('Your offer opens its own page, and an account for you if you have none.'),
                        lang('A coordinator calls you to talk about where you could help.'),
                        lang('A short training, then your first day with a team.'),
                    ), lang('Questions first?'), lang('Who can volunteer, how old one must be and what we pay for: the answers are on one page.'),
                        lang('Read the answers'), 'faq'), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('offer-your-time', lang('volunteer form, sign up, apply'))),
    )),
);

// One offer: what was sent, its status and the messages under it.
$volunteer_pages[] = array(
    'key'      => 'volunteer_view',
    'name'     => lang('volunteering'),
    'folder'   => 'registration',
    'title'    => lang('Volunteering'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your volunteering'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('volunteer_detail'),
        $comments_section(lang('Messages'), 'volunteer_messages'),
    ), false),
);

// My Volunteering: the volunteer's offers (for the staff, everybody's).
$volunteer_pages[] = array(
    'key'     => 'my_volunteering',
    'name'    => lang('my-volunteering'),
    'folder'  => 'registration',
    'title'   => lang('My Volunteering'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_my_page(lang('My Volunteering'), lang('Your offers to volunteer, their status and our messages, newest first.'),
        'my_volunteering_list', lang('Another way to help?'), lang('Offer your time again; your details are filled in for you.'),
        lang('Volunteer'), 'volunteer', 'bi-people'),
);

// The staff's list of every offer, newest first, with a search.
$volunteer_pages[] = array(
    'key'     => 'staff_volunteers',
    'name'    => lang('all-volunteers'),
    'folder'  => 'private',
    'title'   => lang('All Volunteers'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_staff_page(lang('All Volunteers'), lang('Every offer to volunteer, newest first: call the volunteer, set the status and answer under each.'), 'staff_volunteers_list'),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$volunteer_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the charity keeps about donors and volunteers, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the charity keeps about donors and volunteers, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your charity and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you give, make a pledge, offer your time, sign up for an event or the newsletter or open an account, we keep your name, your contact details, what you give and what you tell us, to thank you, send your receipts, arrange your volunteering and answer you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the staff receive, the reply to the volunteer and the
// new message e-mail.
$volunteer_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_volunteer_new',
    'name'  => lang('email-new-volunteer'),
    'title' => lang('New Volunteer E-mail'),
    'tree'  => $mail_page(array($widget('email_volunteer_admin'))),
));
$volunteer_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_volunteer_received',
    'name'  => lang('email-volunteer-received'),
    'title' => lang('Volunteer Received E-mail'),
    'tree'  => $mail_page(array($widget('email_volunteer_client'))),
));
// A new message or update: sent to a volunteer, a pledge's donor, the
// sender of a conversation and the visitors following a campaign or a
// post. Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$volunteer_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your volunteering, your pledge, a campaign, a conversation or a post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// An offer in a list.
$volunteer_row = function ($staff = false) use ($request_row, $volunteer_roles, $volunteer_causes, $volunteer_times) {
    return $request_row(lang('Offer'), 'help_with', $volunteer_roles[0], array(
        array('bi-heart', lang('Cause'), $volunteer_causes[0], 'cause'),
        array('bi-clock', lang('Time'), $volunteer_times[0][1], 'availability'),
        array('bi-geo-alt', lang('City'), lang('Istanbul'), 'city'),
    ), $staff);
};

// The lines of an offer, for its e-mails.
$mail_volunteer = function () use ($mail_box, $mail_line, $span, $bind, $volunteer_roles, $volunteer_causes, $volunteer_times) {
    return $mail_box(array(
        $mail_line(lang('I would like to help'), array($span($volunteer_roles[0], '', lang('Help With'), $bind('help_with')))),
        $mail_line(lang('Cause'), array($span($volunteer_causes[0], '', lang('Cause'), $bind('cause')))),
        $mail_line(lang('Time'), array($span($volunteer_times[0][1], '', lang('Time'), $bind('availability')))),
        $mail_line(lang('City'), array($span(lang('Istanbul'), '', lang('City'), $bind('city')))),
    ), lang('Offer'));
};

$volunteer_widgets = array(

    // The offer: the fields are the page's form. The cause starts with the
    // choice of the page that sent the visitor (?cause=). The Status is the
    // staff's (office use only): every offer starts as Received.
    'volunteer_form' => array(
        'page'   => 'volunteer',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('How you would like to help')),
            $row(array(
                $col(array($field('ch-vo-help', lang('I would like to help'), $select('ch-vo-help', 'help_with', lang('Choose a way to help…'), $volunteer_roles))), '6'),
                $col(array($field('ch-vo-cause', lang('Cause'), $select('ch-vo-cause', 'cause', '', $volunteer_causes, true, $volunteer_causes[0], array('url_param' => 'cause')))), '6'),
            ), '3'),
            $radios('ch-vo-time', 'availability', lang('Time I can give'), $volunteer_times, $volunteer_times[0][0]),
            $field('ch-vo-city', lang('City'), $input('ch-vo-city', 'text', 'city', 'address-level2', false)),
            $field('ch-vo-skills', lang('Your skills and experience'), $textarea('ch-vo-skills', 'skills', 4, false), 'mb-3',
                lang('Your profession, the languages you speak, what you have done before.')),
            $field('ch-vo-cv', lang('CV or certificate'), $file('ch-vo-cv', 'cv', 'private'), 'mb-3',
                lang('If you have one (PDF or a picture). More can follow under your offer.')),
            $form_step(2, lang('About you')),
        ), $request_contact('ch-vo', $volunteer_statuses,
            lang('I agree that the charity keeps these details to arrange my volunteering and answer me, as its privacy notice explains.'),
            lang('Offer My Time'), lang('We call you within a week.')), array(
            $loop(array()),
        ))),
    ),

    // One offer: what was sent and its status; the messages follow as
    // comments.
    'volunteer_detail' => $request_detail_config('volunteer_view', 'volunteer', $volunteer_missing, 'submitter_only') + array(
        'tree' => $request_record_view('bi-people', lang('Offer not found'), $volunteer_missing,
            'my_volunteering', lang('My Volunteering'),
            lang('Thank you for offering your time. We call you within a week; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Offer Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $request_record_title(lang('Volunteering'), $volunteer_roles[0], 'help_with'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $request_detail(lang('Cause'), '', lang('Cause'), $request_bound($volunteer_causes[0], 'cause')),
                            $request_detail(lang('Time I can give'), '', lang('Time'), $request_bound($volunteer_times[0][1], 'availability')),
                            $request_detail(lang('City'), '', lang('City'), $request_bound(lang('Istanbul'), 'city')),
                            $request_detail(lang('Volunteer'), '', lang('Volunteer'), $request_person(), true),
                            $request_detail(lang('CV or certificate'), 'text-break', lang('Documents'), $request_file_link('cv', 'cv.pdf'), true),
                            $request_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $request_bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $request_record_text(lang('Your skills and experience'), 'skills', $sample_volunteer),
                $request_record_note(lang('A question, or a date that suits you? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'volunteer_messages' => $request_messages('volunteer_view',
        lang('No messages yet. Write to us here about your volunteering.'),
        lang('Write to the charity'), lang('Sign in to write to the charity.')),

    'my_volunteering_list' => $request_list('my_volunteering', 'volunteer', 'volunteer_view', $volunteer_row(),
        lang('You have not offered your time yet.')),

    'staff_volunteers_list' => $request_list('staff_volunteers', 'volunteer', 'volunteer_view', $volunteer_row(true),
        lang('No offers to volunteer yet.'),
        array('first_name', 'last_name', 'help_with', 'cause', 'availability', 'city', 'status'),
        lang('Search by name, way to help, cause, city or status...')),

    // The e-mail pages' widgets: drawn for the offer just sent.
    'email_volunteer_admin' => $request_detail_config('email_volunteer_new', 'volunteer', lang('The offer could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new volunteer')),
            $mail_para(lang('It came from the volunteer page. Call the volunteer to talk about where they could help, then set the status on the offer\'s page; what you write under it reaches the volunteer by e-mail.')),
            $mail_volunteer(),
            $mail_sender(array(
                $mail_line(lang('Your skills and experience'), array($span($sample_volunteer, '', lang('Text'), $bind('skills')))),
            )),
            $mail_button(lang('Open the offer'), '{{page:volunteer_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_volunteer_client' => $request_detail_config('email_volunteer_received', 'volunteer', lang('The offer could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your offer to volunteer')),
            $mail_greeting(),
            $mail_para(lang('Thank you for offering your time. A coordinator calls you within a week to talk about where you could help.')),
            $mail_volunteer(),
            $mail_para(lang('A question in the meantime? Write to us on your offer\'s page; our answer comes there too.')),
            $mail_button(lang('Open my offer'), '{{page:volunteer_view}}?r=^^reference_code^^'),
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

return array('pages' => $volunteer_pages, 'widgets' => $volunteer_widgets);
