<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", its sponsorship and guest pitches:
 * the sponsorship page (who listens, the packages, how it works, what past
 * sponsors say), the sponsorship enquiry and the guest pitch, each with its
 * own page and messages, My Enquiries (the visitor's enquiries and pitches)
 * and the staff's lists; the privacy page the consent boxes link, the
 * e-mails of both forms and the new message e-mail every thread of the
 * site sends, and the widgets that draw them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A sponsorship enquiry is a request, never a booked campaign: a record of
 * the Sponsorship Enquiries form. A sender without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once. Its Status is an office-use-only field the network's staff change
 * in the control panel; the proposal is written under it as messages (the
 * page's comments), mailed to the sender and to the network. The package
 * and the podcast start with what a link sends (?package=, ?podcast=: the
 * titles parts.php writes on the package cards).
 *
 * A guest pitch is the same kind of request on the Guest Pitches form: who
 * the guest is, the podcast, the episode's subject and why now. The
 * producers answer under it; an invitation is a message, never a booked
 * recording.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$sponsor_pages = array();

// The sample enquiry and pitch the canvas shows. The start of a
// sponsorship is a month after the day the template opens, never a fixed
// date.
$sp_sample_package = $package_titles[1];
$sp_sample_podcast = $podcast_titles[2];
$sp_sample_company = lang('Seaside Coffee Roasters');
$sp_sample_start = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
$sp_sample_details = lang('We roast coffee for small cafés and would like the listeners of Small Ledger to hear how we started, read by the host across the next season.');
$gp_sample_guest = lang('Elif Şahin');
$gp_sample_podcast = $podcast_titles[0];
$gp_sample_topic = lang('Repairing old radios and the people who bring them in');
$gp_sample_story = lang('Elif has repaired radios in the same small shop for thirty years. Every radio comes in with a story, and she remembers most of them.');
$gp_sample_links = lang('A newspaper interview with her, and a short film about the shop');
$gp_who_choices = array(
    array(lang('Myself'), lang('Myself')),
    array(lang('Someone I know'), lang('Someone I know')),
);

// ── Shells shared by both requests ──────────────────────────────────────

// A request's form page: the header, the form in a card and a side column
// with what happens next and what helps.
$pd_request_page = function ($title, $lead, $widget_key, $steps, $box_title, $box_items)
    use ($page, $page_header, $el, $heading, $container, $row, $col, $widget, $check_list) {
    $items = array();
    foreach (array_values($steps) as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
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
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'ppd-display h5 mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
                        )),
                        $el('div', 'p-4 rounded-4 border', $box_title, array(
                            $heading('h2', $box_title, 'ppd-display h5 mb-3'),
                            $check_list($box_items, 'list-unstyled small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A request's own page: the request, then the messages under it.
$pd_request_view = function ($detail_key, $messages_key) use ($page, $widget, $comments_section) {
    return $page(array(
        $widget($detail_key),
        $comments_section(lang('Messages'), $messages_key),
    ), false);
};
$pd_request_comments = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject, 'notify_email' => '{{site_email}}');
};

// ── The blocks ──────────────────────────────────────────────────────────
// The sponsorship page's packages are the Podcast: Sponsor Placements block
// and its steps the Steps: Numbered block, drawn with the network's data:
// the same structure, classes and interactions, so the rules the template
// ships in User Styles style them as they style a dropped block.

// Where a sponsor's mention plays: the heading fades in, the drawing of one
// episode and of a season slides in (screen readers skip it: the cards say
// the same), then a card per package rises in turn, numbered as on the
// drawing, with where it plays, what it includes, the price it starts from
// and the way to ask about it (the enquiry opens with the package chosen).
// The drawing marks the packages in their order: the opening, the middle,
// the season.
$sp_placements = function () use ($el, $heading, $para, $link, $span, $container, $row, $col, $fx, $attr, $searchable, $check_list,
    $packages, $money, $query) {
    $no = function ($n) use ($span) {
        return $span((string)$n, 'pg-podcast-no', lang('Number'));
    };
    $mark = function ($n, $text, $class = '') use ($el, $span, $no) {
        return $el('div', trim('pg-podcast-at ' . $class), $text, array($no($n), $span($text, '', lang('Text'))));
    };
    $at = array_column(array_values($packages), 'at');
    $cards = array();
    foreach (array_values($packages) as $i => $p) {
        $cards[] = $el('li', 'col-md-4', $p['title'], array(
            $el('div', 'h-100 p-4 rounded-4 border bg-body d-flex flex-column', lang('Card'), array(
                $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Title'), array(
                    $no($i + 1),
                    $heading('h3', $p['title'], 'ppd-display h5 fw-bold mb-0'),
                )),
                $para($p['when'], 'small fw-semibold text-body-emphasis mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $check_list($p['perks'], 'list-unstyled small mb-4'),
                $el('div', 'd-flex flex-wrap align-items-baseline gap-1 mt-auto mb-3', lang('Price'), array(
                    $span(lang('Starting from'), 'small text-body-secondary', lang('Label')),
                    $span($money($p['price']), 'ppd-price fs-4 fw-bold text-body-emphasis', lang('Value')),
                    $span($p['per'], 'small text-body-secondary', lang('Label')),
                )),
                $link(lang('Ask About It'), '{{page:sponsor_enquiry}}?' . $query(array('package' => $p['title'])), 'btn border link-body-emphasis align-self-start'),
            )),
        ));
    }
    $head = $fx($row(array(
        $col(array(
            $para(lang('Packages'), 'ppd-eyebrow small text-uppercase fw-semibold text-primary-emphasis mb-2'),
            $heading('h2', lang('Where your mention plays'), 'ppd-display display-6 fw-bold mb-0'),
        ), '', '6'),
        $col(array(
            $para(lang('Every mention is read by the host, in their own words, at one of three places. Listeners hear it the way they hear the rest of the episode.'), 'lead text-body-secondary mb-0'),
        ), '', '6'),
    ), '3', '', 'align-items-end mb-5'), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800));
    $track = $fx($el('div', 'pg-podcast-track mb-5', lang('Placements Drawing'), array(
        $para(lang('One episode'), 'small fw-semibold text-body-emphasis mb-2'),
        $el('div', 'pg-podcast-bar', lang('Episode')),
        $mark(1, $at[0]),
        $mark(2, $at[1], 'pg-podcast-at-mid'),
        $para(lang('A season'), 'small fw-semibold text-body-emphasis mt-4 mb-2'),
        $el('div', 'pg-podcast-season', lang('A season')),
        $mark(3, $at[2]),
    ), array('_attrs' => array($attr('aria-hidden', 'true')))), array('effect' => 'slide-right', 'trigger' => 'view', 'duration' => 900, 'delay' => 150, 'distance' => 60));
    $list = $fx($el('ol', 'row g-4 list-unstyled mb-4', lang('Placements'), $cards),
        array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'delay' => 300, 'stagger' => 120));
    return $el('section', 'py-5 border-top overflow-hidden', lang('Podcast (Sponsor Placements)'), array(
        $container(array(
            $head,
            $track,
            $list,
            $para(lang('Prices are where a package starts; the proposal names the podcast, the episodes and the final price.'), 'small text-body-secondary mb-4'),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Call to Action'), array(
                $link(lang('Send a Sponsorship Enquiry'), '{{page:sponsor_enquiry}}', 'btn btn-primary btn-lg px-4'),
                $para(lang('Nothing is booked until you agree to the proposal.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), $searchable('pd-sponsor-packages', lang('prices, rates, advertising, host-read ads, mid-roll, pre-roll')));
};

// How a sponsorship comes together, the Steps: Numbered block: a card per
// step with its number and icon; the heading fades in and the cards rise
// in turn.
$sp_steps = function () use ($el, $heading, $para, $span, $icon, $container, $row, $col, $fx, $rise, $fade_in, $searchable) {
    $steps = array(
        array('bi-envelope-paper', lang('You send an enquiry'), lang('The package, the podcast that fits and what you would like listeners to hear.')),
        array('bi-file-earmark-text', lang('We send a proposal'), lang('Within three working days: the episodes, the dates, the words and the price, under your enquiry.')),
        array('bi-mic', lang('The host reads it'), lang('In their own words, with your link in the episode notes and a report of listens afterwards.')),
    );
    $cards = array();
    foreach ($steps as $n => $s) {
        $cards[] = $col(array(
            $el('div', 'h-100 p-4 rounded-4 border bg-body', lang('Step Card'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Step Head'), array(
                    $span((string)($n + 1), 'badge rounded-pill text-bg-primary fs-6 px-3 py-2', lang('Step Number')),
                    $icon($s[0], 'text-primary fs-4'),
                )),
                $heading('h3', $s[1], 'h6 fw-bold mb-2'),
                $para($s[2], 'small text-body-secondary mb-0'),
            )),
        ), '4');
    }
    return $el('section', 'py-5 bg-body-tertiary', lang('Steps (Numbered)'), array(
        $container(array(
            $fx($el('div', 'text-center mb-5', lang('Section Header'), array(
                $heading('h2', lang('From a first message to the episode'), 'ppd-display fw-bold mb-2'),
                $para(lang('A sponsorship takes a few days to agree and nothing is booked until you say yes.'), 'lead text-body-secondary mb-0'),
            )), $fade_in),
            $rise($row($cards, '4'), 120),
        )),
    ), $searchable('pd-sponsor-steps', lang('how to sponsor, proposal, booking an ad')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The sponsorship page: who listens, where a mention plays and the
// packages, how it works and what past sponsors say, then the way to an
// enquiry.
$sp_figures = array();
foreach (array_keys($audience) as $sp_n) $sp_figures[] = $figure_tile($sp_n);
$sp_quote_cards = array();
foreach (array_keys($sponsor_quotes) as $sp_n) $sp_quote_cards[] = $sponsor_quote_card($sp_n);

$sponsor_pages[] = array(
    'key'              => 'sponsor',
    'name'             => lang('sponsorship'),
    'folder'           => 'public',
    'title'            => lang('Sponsorship'),
    'meta_description' => lang('Sponsor an episode or a season: who listens to the network, the packages and their prices, and how a sponsorship comes together.'),
    'tree'             => $page(array(
        $page_header(lang('Sponsorship'),
            lang('Our listeners chose to hear every word. Tell them about your brand in the host\'s own voice, on a podcast that fits it.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Send a Sponsorship Enquiry'), '{{page:sponsor_enquiry}}', 'btn btn-primary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Who Listens'), array(
            $container(array(
                $rise($row(array(
                    $col(array(
                        $para(lang('Who listens'), 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('An audience that chose to listen'), 'ppd-display display-6 mb-3'),
                        $para(lang('Our listeners follow the podcasts in their apps, hear most of each episode and write to the hosts. Many of them support the network themselves.'), 'text-body-secondary mb-3'),
                        $check_list(array(
                            lang('Mostly between 25 and 44, living in cities'),
                            lang('Curious about culture, technology and small business'),
                            lang('Listening on the way to work, in the kitchen and at night'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-body-tertiary', lang('Figures'), array($row($sp_figures, '4'))),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'), 150),
            )),
        ), $searchable('pd-sponsor-audience', lang('listeners, audience, reach, downloads, demographics'))),
        $sp_placements(),
        $sp_steps(),
        $el('section', 'py-5', lang('Past Sponsors'), array(
            $container(array(
                $fx($section_header(lang('In their words'), lang('What past sponsors say')), $fade_in),
                $rise($row($sp_quote_cards, '4'), 150),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Call to Action'), array(
            $container(array(
                $fx($el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Tell us about your brand'), 'ppd-display h3 mb-1'),
                        $para(lang('Send an enquiry and we answer with a proposal, or write to our sponsorship team.'), 'text-body-secondary mb-0'),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Send a Sponsorship Enquiry'), '{{page:sponsor_enquiry}}', 'btn btn-primary rounded-pill px-4'),
                        $link($network['sponsorship'], 'mailto:' . $network['sponsorship'], 'btn border rounded-pill px-4 link-body-emphasis'),
                    )),
                )), $fade_in),
            )),
        )),
    )),
);

// The sponsorship enquiry. What is sent opens the enquiry's own page.
$sponsor_pages[] = array(
    'key'              => 'sponsor_enquiry',
    'name'             => lang('sponsorship-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Sponsorship Enquiry'),
    'meta_description' => lang('Tell us about your brand, the package and the podcast that fits it; we answer every sponsorship enquiry with a proposal within three working days.'),
    'form'             => array(
        'form_name'            => lang('Sponsorship Enquiries'),
        'confirmation_message' => lang('Thank you, your sponsorship enquiry has reached us. We answer with a proposal within three working days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New sponsorship enquiry: ^^company^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your sponsorship enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $pd_request_page(lang('Sponsorship Enquiry'),
        lang('Choose a package and the podcast that fits your brand, and tell us what listeners should hear. We answer with a proposal within three working days.'),
        'sponsor_enquiry_form',
        array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('Within three working days we write a proposal under it: the episodes, the dates and the price.'),
            lang('You agree to it, or ask for changes, on the same page.'),
            lang('The host reads your mention and we send you a report of listens.'),
        ), lang('Good to know'), array(
            lang('Nothing is booked until you agree to the proposal.'),
            lang('The host only reads what they would recommend.'),
            lang('Your brief is seen only by the network.'),
        )),
);

// One sponsorship enquiry: the enquiry, its status and the messages under it.
$sponsor_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('sponsorship-enquiry-details'),
    'folder'   => 'registration',
    'title'    => lang('Sponsorship Enquiry Details'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $pd_request_comments(lang('There is a new message about your sponsorship enquiry')),
    'tree'     => $pd_request_view('sponsor_enquiry_detail', 'sponsor_enquiry_messages'),
);

// The guest pitch. What is sent opens the pitch's own page.
$sponsor_pages[] = array(
    'key'              => 'guest_pitch',
    'name'             => lang('be-a-guest'),
    'folder'           => 'public',
    'title'            => lang('Be a Guest'),
    'meta_description' => lang('Suggest a guest for one of our podcasts, yourself or someone you know: who they are, what the episode would be about and why now.'),
    'form'             => array(
        'form_name'            => lang('Guest Pitches'),
        'confirmation_message' => lang('Thank you, your guest pitch has reached us. Our producers read every pitch and answer within two weeks.'),
        'confirmation_page_id' => '{{tab:pitch_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New guest pitch: ^^guest_name^^'),
        'notify_page_id'       => '{{tab:email_pitch_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your guest pitch'),
        'confirm_page_id'      => '{{tab:email_pitch_received}}',
    ),
    'tree'             => $pd_request_page(lang('Be a Guest'),
        lang('Know someone with a story our listeners should hear, or have one yourself? Tell us who, for which podcast and why now.'),
        'guest_pitch_form',
        array(
            lang('Your pitch opens its own page, and an account for you if you have none.'),
            lang('Our producers read every pitch and answer under it within two weeks.'),
            lang('If it fits a podcast, we invite the guest and agree a day to record.'),
        ), lang('What makes a good pitch'), array(
            lang('One clear subject, not a whole life story.'),
            lang('Something the guest knows first hand.'),
            lang('A reason listeners should hear it now.'),
        )),
);

// One guest pitch: the pitch, its status and the messages under it.
$sponsor_pages[] = array(
    'key'      => 'pitch_view',
    'name'     => lang('guest-pitch-details'),
    'folder'   => 'registration',
    'title'    => lang('Guest Pitch Details'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $pd_request_comments(lang('There is a new message about your guest pitch')),
    'tree'     => $pd_request_view('guest_pitch_detail', 'guest_pitch_messages'),
);

// My Enquiries: the visitor's own sponsorship enquiries and guest pitches
// (for the staff, everybody's), with the way to send another.
$sponsor_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your sponsorship enquiries and guest pitches, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Sponsorship Enquiries'), 'ppd-display h4 mb-3'),
                        $widget('my_enquiries_list'),
                        $heading('h2', lang('Guest Pitches'), 'ppd-display h4 mt-5 mb-3'),
                        $widget('my_pitches_list'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle'),
                                $span(lang('Something new?'), '', lang('Text')),
                            )),
                            $para(lang('Send another enquiry or pitch; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Sponsor an Episode'), '{{page:sponsor_enquiry}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                                $link(lang('Be a Guest'), '{{page:guest_pitch}}', 'btn btn-sm btn-secondary rounded-pill px-3'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's lists of every enquiry and every pitch, newest first, with a
// search.
$sponsor_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-sponsorship-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Sponsorship Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Sponsorship Enquiries'), lang('Every sponsorship enquiry, newest first: write the proposal under each, then set the status.')),
        $el('section', 'py-5', lang('Content'), array($container(array($widget('staff_enquiries_list'))))),
    )),
);
$sponsor_pages[] = array(
    'key'     => 'staff_pitches',
    'name'    => lang('all-guest-pitches'),
    'folder'  => 'private',
    'title'   => lang('Guest Pitches'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Guest Pitches'), lang('Every guest pitch, newest first: answer under each, invite the guests that fit and set the status.')),
        $el('section', 'py-5', lang('Content'), array($container(array($widget('staff_pitches_list'))))),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$sponsor_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the network keeps about you when you join the newsletter, send a sponsorship enquiry or a guest pitch, or support us, and why.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the network keeps about you when you join the newsletter, send a sponsorship enquiry or a guest pitch, or support us, and why.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'ppd-display h4 mb-3'),
                $para(lang('When you join the newsletter, send a sponsorship enquiry or a guest pitch, write to us, support the network or open an account, we keep your name, your contact details, what you tell us and the files you send, to answer you, prepare a proposal or an episode and send you the newsletter if you asked for it.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers or sponsors. You can see and change your account data from My Account, leave the newsletter from any of its e-mails, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('pd-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the network receives and the reply to the sender of
// each request, and the new message e-mail.
foreach (array(
    array('email_enquiry_new', lang('email-new-sponsorship-enquiry'), lang('New Sponsorship Enquiry E-mail'), 'email_enquiry_admin'),
    array('email_enquiry_received', lang('email-sponsorship-enquiry-received'), lang('Sponsorship Enquiry Received E-mail'), 'email_enquiry_client'),
    array('email_pitch_new', lang('email-new-guest-pitch'), lang('New Guest Pitch E-mail'), 'email_pitch_admin'),
    array('email_pitch_received', lang('email-guest-pitch-received'), lang('Guest Pitch Received E-mail'), 'email_pitch_client'),
) as $sp_mail) {
    $sponsor_pages[] = array_merge($mail_settings, array(
        'key'   => $sp_mail[0],
        'name'  => $sp_mail[1],
        'title' => $sp_mail[2],
        'tree'  => $mail_page(array($widget($sp_mail[3]))),
    ));
}
// A new message or reply: sent to the sender of an enquiry or a pitch, the
// sender of a conversation and the visitors following a page. Its Comments
// widget draws the comment in the card of mail.php; the System region after
// it keeps the fixed comment for an e-mail the widget does not draw.
$sponsor_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your sponsorship enquiry, your guest pitch, a conversation, an episode or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$pd_sender_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A small detail of a list row: an icon, then its words.
$pd_row_item = function ($icon_name, $name, $sample, $token) use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array(
        $icon($icon_name),
        $span($sample, '', $name, $bind($token)),
    ));
};

// A request in a list: its title and status on top, the details under
// them, linked to its page. The staff's row starts with the sender's name
// and adds the phone; the sender's own adds the messages.
$pd_request_row = function ($staff, $title, $token, $items, $status, $name) use ($el, $span, $bind, $pd_sender_name, $pd_row_item) {
    if ($staff) {
        $items[] = $pd_row_item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $top = $el('span', 'fw-semibold', lang('Sent by'), array_merge($pd_sender_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title, 'text-body-secondary fw-normal', $name, $bind($token)),
        )));
    } else {
        $items[] = $pd_row_item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $top = $span($title, 'fw-semibold', $name, $bind($token));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $top,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A list of one form's requests: the sender's own (the staff see them all)
// or, with $search, the staff's list with a search over those fields.
$pd_request_list = function ($page_key, $form_key, $view_key, $empty, $row_tree, $search = array(), $search_label = '')
    use ($widget_root, $el, $loop, $pd_list_config) {
    $more = array('items_per_page' => 20);
    if ($search) {
        $more += array('search_enabled' => true, 'search_fields' => $search, 'search_width' => 'half', 'search_label' => $search_label);
    } else {
        $more += array('viewer_filter' => true, 'viewer_filter_submitter' => true, 'viewer_filter_watcher' => true, 'viewer_filter_editor' => true);
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $pd_list_config($form_key, $view_key, 'submitted_date_and_time', 'DESC', $empty, $more),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row_tree)))),
        )),
    );
};

// The form detail settings of a request page's widget (the sender's own,
// and the staff's) and of an e-mail page's widget (any reader: the e-mail
// is drawn for the submission just sent).
$pd_detail_widget = function ($page_key, $form_key, $missing, $access) {
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

// A detail of the request's card: the label, then the value; $value is
// the sample bound to $token, or the nodes the value is made of.
$pd_detail = function ($label, $value, $token = '', $class = '') use ($el, $bind) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        is_array($value)
            ? $el('dd', trim('col-sm-8 ' . $class), $label, $value)
            : $el('dd', trim('col-sm-8 ' . $class), $label, array(), array_merge(array('text' => $value), $bind($token))),
    );
};
// A file or an address the request holds, as a link in a new window.
$pd_detail_link = function ($label, $sample, $token) use ($pd_detail, $link) {
    return $pd_detail($label, array(
        $link($sample, '#', 'link-primary', array(), array('target' => '_blank', 'rel' => 'noopener nofollow',
            '_bindings' => array('href' => $token, 'text' => $token))),
    ), '', 'text-break');
};

// The request page's tree: the not-found note, the sent and new-account
// notices, the back link and the staff's status button, the card with the
// details, the sender's own words and a note on the messages under it. $c
// carries one form's words and fields; 'words' lists the long fields as
// array(label, sample, field).
$pd_request_detail = function ($c) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button, $pd_not_found) {
    $words = array();
    foreach ($c['words'] as $w) {
        $words[] = $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $w[0], array(
            $para($w[0], 'small fw-semibold text-body-secondary mb-2'),
            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $w[1]), $bind($w[2]))),
        ));
    }
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $pd_not_found('bi-file-earmark-x', $c['not_found_title'], $c['missing'], lang('My Enquiries'), 'my_enquiries'),
                    $el('div', '', lang('Record'), array_merge(array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($c['sent'], '', lang('Text')),
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
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($c['eyebrow'], 'ppd-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $c['title'], 'ppd-display h3 mb-0', $bind($c['title_field'])),
                                    )),
                                    $span($c['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $c['details']),
                            )),
                        )),
                    ), $words, array(
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($c['note'], '', lang('Text')),
                        )),
                    )), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the network every message.
$pd_request_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The second step both forms end with: the sender's name, e-mail and phone
// ($more adds columns between them), how to reach them, the staff's status,
// consent, CAPTCHA and the send button. $p is the id prefix.
$pd_request_contact = function ($p, $more, $statuses, $consent, $send, $answer)
    use ($el, $para, $row, $col, $field, $input, $select, $radios, $agree, $captcha, $submit, $form_step, $loop, $contact_choices) {
    return array(
        $form_step(2, lang('How to reach you')),
        $row(array_merge(array(
            $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        ), $more, array(
            $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        )), '3'),
        $radios($p . '-contact', 'contact_by', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
        $field($p . '-status', lang('Status'), $select($p . '-status', 'status', '', $statuses, false, $statuses[0], array('office_use_only' => true))),
        $agree($p . '-consent', 'consent', $consent, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($send, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($answer, 'small text-body-secondary mb-0'),
        )),
        $loop(array()),
    );
};

// The sender's lines of a staff e-mail; their own words under the label.
$pd_mail_sender = function ($words_label, $words_sample, $words_token) use ($mail_box, $mail_line, $span, $bind, $pd_sender_name, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Sent by'), $pd_sender_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span($contact_choices[0][1], '', lang('Contact by'), $bind('contact_by')))),
        $mail_line($words_label, array($span($words_sample, '', $words_label, $bind($words_token)))),
    ), lang('Sent by'));
};
// Lines of a request for its e-mails: each array(label, sample, field).
$pd_mail_lines = function ($lines, $name) use ($mail_box, $mail_line, $span, $bind) {
    $children = array();
    foreach ($lines as $l) $children[] = $mail_line($l[0], array($span($l[1], '', $l[0], $bind($l[2]))));
    return $mail_box($children, $name);
};

// ── One form's words and fields ─────────────────────────────────────────

$sp_missing = lang('This sponsorship enquiry could not be found, or it is not yours to see.');
$gp_missing = lang('This guest pitch could not be found, or it is not yours to see.');

$sp_row = function ($staff) use ($pd_request_row, $pd_row_item, $sp_sample_company, $sp_sample_package, $sp_sample_podcast, $budget_choices, $enquiry_statuses) {
    return $pd_request_row($staff, $sp_sample_company, 'company', array(
        $pd_row_item('bi-megaphone', lang('Package'), $sp_sample_package, 'package'),
        $pd_row_item('bi-mic', lang('Podcast'), $sp_sample_podcast, 'podcast'),
        $pd_row_item('bi-cash-coin', lang('Budget'), $budget_choices[1], 'budget'),
    ), $enquiry_statuses[0], lang('Sponsorship Enquiry'));
};
$gp_row = function ($staff) use ($pd_request_row, $pd_row_item, $gp_sample_guest, $gp_sample_podcast, $gp_sample_topic, $pitch_statuses) {
    return $pd_request_row($staff, $gp_sample_guest, 'guest_name', array(
        $pd_row_item('bi-mic', lang('Podcast'), $gp_sample_podcast, 'podcast'),
        $pd_row_item('bi-chat-quote', lang('Topic'), $gp_sample_topic, 'topic'),
    ), $pitch_statuses[0], lang('Guest Pitch'));
};

$sp_mail_lines = $pd_mail_lines(array(
    array(lang('Package'), $sp_sample_package, 'package'),
    array(lang('Podcast'), $sp_sample_podcast, 'podcast'),
    array(lang('Goal'), $sponsor_goals[1], 'goal'),
    array(lang('Budget'), $budget_choices[1], 'budget'),
    array(lang('Start'), $sp_sample_start, 'start'),
    array(lang('Company'), $sp_sample_company, 'company'),
    array(lang('Website'), 'https://example.com', 'website'),
), lang('Sponsorship Enquiry'));
$gp_mail_lines = $pd_mail_lines(array(
    array(lang('Guest Name'), $gp_sample_guest, 'guest_name'),
    array(lang('Who is the guest?'), $gp_who_choices[1][1], 'who'),
    array(lang('Podcast'), $gp_sample_podcast, 'podcast'),
    array(lang('Topic'), $gp_sample_topic, 'topic'),
    array(lang('Links'), $gp_sample_links, 'links'),
), lang('Guest Pitch'));

$sponsor_widgets = array(

    // The sponsorship enquiry: the fields are the page's form. The package
    // and the podcast start with what a link sends (?package=, ?podcast=).
    // The Status is the network's (office use only): every enquiry starts
    // as Enquiry received.
    'sponsor_enquiry_form' => array(
        'page'   => 'sponsor_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your sponsorship')),
            $row(array(
                $col(array($field('pd-se-package', lang('Package'), $select('pd-se-package', 'package', lang('Choose a package…'), $package_titles, true, '', array('url_param' => 'package')))), '6'),
                $col(array($field('pd-se-podcast', lang('Podcast'), $select('pd-se-podcast', 'podcast', lang('Any podcast that fits'), $podcast_titles, false, '', array('url_param' => 'podcast')))), '6'),
                $col(array($field('pd-se-goal', lang('Goal'), $select('pd-se-goal', 'goal', lang('Choose a goal…'), $sponsor_goals))), '6'),
                $col(array($field('pd-se-budget', lang('Budget'), $select('pd-se-budget', 'budget', '', $budget_choices, false, $budget_choices[3]))), '6'),
            ), '3'),
            $field('pd-se-start', lang('Start'), $input('pd-se-start', 'date', 'start', 'off', false, array('rules' => array('min_date' => 'today'))), 'mb-3',
                lang('When you would like to start.')),
            $field('pd-se-details', lang('About your brand'), $textarea('pd-se-details', 'details', 4), 'mb-3',
                lang('What you make or sell, who you would like to reach and what listeners should hear.')),
            $field('pd-se-attachment', lang('Attachment'), $file('pd-se-attachment', 'attachment', 'private'), 'mb-3',
                lang('A brief or a media kit, if you have one.')),
        ), $pd_request_contact('pd-se', array(
            $col(array($field('pd-se-company', lang('Company'), $input('pd-se-company', 'text', 'company', 'organization', true, array('contact_field' => 'company')))), '6'),
            $col(array($field('pd-se-website', lang('Website'), $input('pd-se-website', 'url', 'website', 'url', false))), '6'),
        ), $enquiry_statuses,
            lang('I agree that the network keeps these details to answer my enquiry and prepare a proposal, as its privacy notice explains.'),
            lang('Send the Enquiry'), lang('We answer with a proposal within three working days.')))),
    ),

    // One sponsorship enquiry: the enquiry, its status and what the sender
    // wrote; the network's answers (the proposal among them) follow as
    // comments.
    'sponsor_enquiry_detail' => $pd_detail_widget('enquiry_view', 'sponsor_enquiry', $sp_missing, 'submitter_only') + array(
        'tree' => $pd_request_detail(array(
            'not_found_title' => lang('Sponsorship enquiry not found'),
            'missing'         => $sp_missing,
            'sent'            => lang('Thank you, your enquiry has reached us. We write a proposal here within three working days; this page shows the status, and you can write to us below.'),
            'eyebrow'         => lang('Sponsorship Enquiry'),
            'title'           => $sp_sample_company,
            'title_field'     => 'company',
            'status'          => $enquiry_statuses[0],
            'details'         => array_merge(
                $pd_detail(lang('Package'), $sp_sample_package, 'package'),
                $pd_detail(lang('Podcast'), $sp_sample_podcast, 'podcast'),
                $pd_detail(lang('Goal'), $sponsor_goals[1], 'goal'),
                $pd_detail(lang('Budget'), $budget_choices[1], 'budget'),
                $pd_detail(lang('Start'), $sp_sample_start, 'start'),
                $pd_detail(lang('Sent by'), $pd_sender_name()),
                $pd_detail_link(lang('Website'), 'https://example.com', 'website'),
                $pd_detail(lang('Contact by'), $contact_choices[0][1], 'contact_by'),
                $pd_detail_link(lang('Attachment'), 'media-kit.pdf', 'attachment'),
                $pd_detail(lang('Reference Code'), 'ABC123', 'reference_code', 'mb-0 font-monospace')
            ),
            'words'           => array(array(lang('About your brand'), $sp_sample_details, 'details')),
            'note'            => lang('A question about the proposal, other dates or another podcast? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'sponsor_enquiry_messages' => $pd_request_messages('enquiry_view',
        lang('No messages yet. Our proposal comes here; you can write to us about the sponsorship too.')),

    // The guest pitch: who the guest is, the podcast (?podcast=), the
    // subject and why now. The Status is the producers': every pitch
    // starts as Pitch received.
    'guest_pitch_form' => array(
        'page'   => 'guest_pitch',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('The guest')),
            $radios('pd-gp-who', 'who', lang('Who is the guest?'), $gp_who_choices, $gp_who_choices[0][0]),
            $row(array(
                $col(array($field('pd-gp-guest', lang('Guest Name'), $input('pd-gp-guest', 'text', 'guest_name', 'off'))), '6'),
                $col(array($field('pd-gp-podcast', lang('Podcast'), $select('pd-gp-podcast', 'podcast', lang('Choose a podcast…'), $podcast_titles, true, '', array('url_param' => 'podcast')))), '6'),
            ), '3'),
            $field('pd-gp-topic', lang('Topic'), $input('pd-gp-topic', 'text', 'topic', 'off'), 'mb-3',
                lang('What the episode would be about, in one line.')),
            $field('pd-gp-story', lang('Why now'), $textarea('pd-gp-story', 'story', 4), 'mb-3',
                lang('What the guest knows first hand, and why listeners should hear it now.')),
            $field('pd-gp-links', lang('Links'), $textarea('pd-gp-links', 'links', 3, false), 'mb-3',
                lang('Links to their work, talks or earlier interviews, one per line.')),
            $field('pd-gp-attachment', lang('Attachment'), $file('pd-gp-attachment', 'attachment', 'private'), 'mb-3',
                lang('A one-page bio or a media kit, if there is one.')),
        ), $pd_request_contact('pd-gp', array(), $pitch_statuses,
            lang('I agree that the network keeps these details to consider my pitch and contact the guest, as its privacy notice explains.'),
            lang('Send the Pitch'), lang('Our producers answer within two weeks.')))),
    ),

    'guest_pitch_detail' => $pd_detail_widget('pitch_view', 'guest_pitch', $gp_missing, 'submitter_only') + array(
        'tree' => $pd_request_detail(array(
            'not_found_title' => lang('Guest pitch not found'),
            'missing'         => $gp_missing,
            'sent'            => lang('Thank you, your pitch has reached us. Our producers answer here within two weeks; this page shows the status, and you can write to us below.'),
            'eyebrow'         => lang('Guest Pitch'),
            'title'           => $gp_sample_guest,
            'title_field'     => 'guest_name',
            'status'          => $pitch_statuses[0],
            'details'         => array_merge(
                $pd_detail(lang('Podcast'), $gp_sample_podcast, 'podcast'),
                $pd_detail(lang('Topic'), $gp_sample_topic, 'topic'),
                $pd_detail(lang('Who is the guest?'), $gp_who_choices[1][1], 'who'),
                $pd_detail(lang('Sent by'), $pd_sender_name()),
                $pd_detail(lang('Contact by'), $contact_choices[0][1], 'contact_by'),
                $pd_detail_link(lang('Attachment'), 'bio.pdf', 'attachment'),
                $pd_detail(lang('Reference Code'), 'ABC123', 'reference_code', 'mb-0 font-monospace')
            ),
            'words'           => array(
                array(lang('Why now'), $gp_sample_story, 'story'),
                array(lang('Links'), $gp_sample_links, 'links'),
            ),
            'note'            => lang('More about the guest, or a question? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'guest_pitch_messages' => $pd_request_messages('pitch_view',
        lang('No messages yet. Our producers\' answer comes here; you can write to us about the guest too.')),

    'my_enquiries_list' => $pd_request_list('my_enquiries', 'sponsor_enquiry', 'enquiry_view',
        lang('No sponsorship enquiries yet. Choose a package and tell us about your brand.'), $sp_row(false)),

    'my_pitches_list' => $pd_request_list('my_enquiries', 'guest_pitch', 'pitch_view',
        lang('No guest pitches yet. Know someone our listeners should hear?'), $gp_row(false)),

    'staff_enquiries_list' => $pd_request_list('staff_enquiries', 'sponsor_enquiry', 'enquiry_view',
        lang('No sponsorship enquiries yet.'), $sp_row(true),
        array('first_name', 'last_name', 'email', 'company', 'package', 'podcast', 'status'),
        lang('Search by name, e-mail, company, package, podcast or status...')),

    'staff_pitches_list' => $pd_request_list('staff_pitches', 'guest_pitch', 'pitch_view',
        lang('No guest pitches yet.'), $gp_row(true),
        array('first_name', 'last_name', 'email', 'guest_name', 'podcast', 'topic', 'status'),
        lang('Search by name, e-mail, guest, podcast, topic or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $pd_detail_widget('email_enquiry_new', 'sponsor_enquiry', lang('The sponsorship enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new sponsorship enquiry')),
            $mail_para(lang('It came from the sponsorship form. Write the proposal under the enquiry on its page and set the status; what you write there reaches the sender by e-mail.')),
            $sp_mail_lines,
            $pd_mail_sender(lang('About your brand'), $sp_sample_details, 'details'),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $pd_detail_widget('email_enquiry_received', 'sponsor_enquiry', lang('The sponsorship enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your sponsorship enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We look at the package and the podcast you chose and write a proposal within three working days, on the enquiry\'s page and by e-mail.')),
            $sp_mail_lines,
            $mail_para(lang('Other dates or a question? Write to us on the enquiry\'s page; everything about the sponsorship stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_pitch_admin' => $pd_detail_widget('email_pitch_new', 'guest_pitch', lang('The guest pitch could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new guest pitch')),
            $mail_para(lang('It came from the guest pitch form. Answer under the pitch on its page and set the status; what you write there reaches the sender by e-mail.')),
            $gp_mail_lines,
            $pd_mail_sender(lang('Why now'), $gp_sample_story, 'story'),
            $mail_button(lang('Open the pitch'), '{{page:pitch_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_pitch_client' => $pd_detail_widget('email_pitch_received', 'guest_pitch', lang('The guest pitch could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your guest pitch')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Our producers read every pitch and answer within two weeks, on the pitch\'s page and by e-mail.')),
            $gp_mail_lines,
            $mail_button(lang('Open my pitch'), '{{page:pitch_view}}?r=^^reference_code^^'),
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

return array('pages' => $sponsor_pages, 'widgets' => $sponsor_widgets);
