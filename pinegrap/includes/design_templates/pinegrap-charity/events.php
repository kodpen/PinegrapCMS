<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", its events and reports: the coming events with
 * the short sign-up form, an event's own page, the staff's form for a new
 * event with the sample events, the home page's next events, the staff's
 * form for a new report and the transparency page's list of reports.
 * Included after parts.php, mail.php and forms.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form (a collection), as the Events
 * template's: the samples are dated from the day the template is opened,
 * and the lists show the events from today on, so a past one drops out by
 * itself. Joining one needs no seat and no ticket: every Sign up button
 * opens the events page with the event named (?event=, the sign-up form's
 * url_param), and the sign-up is a record of the Event Sign-ups form that
 * the staff are e-mailed about.
 *
 * A report is a record of the Reports collection: its title, kind, a line
 * on what it covers and its file, kept in the template's public reports
 * folder. The samples carry no file, so their download link stays hidden
 * until the staff add one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$event_pages = array();

// ── The events and their samples ────────────────────────────────────────

// The first day after today with the ISO weekday given (1 = Monday), plus
// $weeks weeks: the samples are still to come whenever the template is
// opened, and no fixed date is written anywhere.
$event_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks > 0) $day->modify('+' . (7 * $weeks) . ' days');
    return $day->format('Y-m-d');
};
$event_kinds = array(lang('Fundraiser'), lang('Open evening'), lang('Collection day'), lang('Talk'));
$event_samples = array(
    array('title' => lang('Charity Run by the River'), 'event_date' => $event_on(7, 2), 'event_time' => '09:00', 'kind' => lang('Fundraiser'),
          'location' => lang('Riverside Park, main gate'),
          'summary' => lang('Five or ten kilometres along the river, at your own pace. Every runner\'s sponsors pay for a well in the Dry Valley.'),
          'image' => $photo('event-run', 800, 450)),
    array('title' => lang('Volunteer Open Evening'), 'event_date' => $event_on(3), 'event_time' => '18:30', 'kind' => lang('Open evening'),
          'location' => lang('Online'),
          'summary' => lang('Meet our coordinators and volunteers back from the field, and ask anything about giving your time.'),
          'image' => $photo('event-open-evening', 800, 450)),
    array('title' => lang('Winter Coat Collection'), 'event_date' => $event_on(6, 1), 'event_time' => '10:00', 'kind' => lang('Collection day'),
          'location' => lang('Our office, ground floor'),
          'summary' => lang('Bring a clean, warm coat or a blanket; our relief team takes them to families before the cold sets in.'),
          'image' => $photo('event-collection', 800, 450)),
    array('title' => lang('Back from the Field: Clean Water'), 'event_date' => $event_on(4, 3), 'event_time' => '19:00', 'kind' => lang('Talk'),
          'location' => lang('City Library, lecture hall'),
          'summary' => lang('Our water engineer shows what a well changes in a village, with photos, figures and time for your questions.'),
          'image' => $photo('event-talk', 800, 450)),
);
// The first sample's day: the text the cards show where the record's own
// date is drawn.
$event_sample_day = new DateTime($event_samples[1]['event_date']);
$event_sample = $event_samples[1];

// An event of the lists: its photo with the date on a leaf over it, the
// kind, the title (the link to its page), when and where, the words and
// the Sign up button.
$event_card = function () use ($el, $span, $para, $icon, $col, $image, $bind, $bind_date, $attr, $link, $page_link, $event_sample, $event_sample_day) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pch-lift', lang('Event Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($event_sample['image'], $event_sample['title'], 'object-fit-cover',
                        array('_bindings' => array('src' => 'image', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('div', 'position-absolute top-0 start-0 m-3 text-center rounded-3 bg-body text-body px-3 py-2 shadow-sm', lang('Date Leaf'), array(
                    $span($event_sample_day->format('d'), 'd-block fs-4 fw-bold lh-1', lang('Day'), $bind_date('event_date', 'd')),
                    $span($event_sample_day->format('m.Y'), 'd-block small fw-semibold text-primary mt-1', lang('Month'), $bind_date('event_date', 'm.Y')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_sample['kind'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis align-self-start mb-2', lang('Kind'), $bind('kind')),
                $el('h3', 'h5 fw-bold mb-2', lang('Event Title'), array(
                    $link($event_sample['title'], '#', 'link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('When and Where'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span($event_sample['event_time'], '', lang('Time'), $bind('event_time')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Location'), array($icon('bi-geo-alt'), $span($event_sample['location'], '', lang('Location'), $bind('location')))),
                )),
                $para($event_sample['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'mt-auto', lang('Card Footer'), array(
                    $link(lang('Sign up'), '{{page:events}}', 'btn btn-sm btn-outline-primary rounded-pill px-3', array(),
                        $page_link('events', array('event' => 'title'))),
                )),
            )),
        )),
    ), '6', '4');
};

// The events lists: from today on, the nearest first.
$events_list = function ($page_key, $count) use ($widget_root, $row, $loop, $event_card) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event_view}}',
        'items_per_page'      => $count,
        'order_by_field'      => 'event_date',
        'order_by_direction'  => 'ASC',
        'filters'             => array(
            array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
        ),
        'empty_message'       => lang('No events planned right now. Subscribe to our newsletter to hear about the next ones.'),
    );
    if ($page_key === 'home') $config['max_results'] = $count;
    return array(
        'page'   => $page_key,
        'slug'   => lang('coming-events'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $row(array($loop(array($event_card()))), '4'),
        )),
    );
};

// ── The reports and their samples ───────────────────────────────────────

$report_kinds = array(lang('Annual report'), lang('Audited accounts'), lang('Impact report'));
$report_samples = array(
    array('title' => lang('Annual Report'), 'kind' => $report_kinds[0],
          'summary' => lang('What we built last year, cause by cause, with the stories and the figures behind them.')),
    array('title' => lang('Audited Accounts'), 'kind' => $report_kinds[1],
          'summary' => lang('Our full accounts, checked by an independent auditor: every gift in, every payment out.')),
    array('title' => lang('Clean Water Impact Report'), 'kind' => $report_kinds[2],
          'summary' => lang('The wells we drilled, the people who use them and what changed in their villages.')),
);

// A report of the transparency page's list: its kind, title and line, and
// the link to its file, which leaves with its row when there is none.
$report_card = function () use ($el, $span, $para, $icon, $col, $link, $bind, $report_samples) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Report Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-center gap-2 small fw-semibold text-primary mb-2', lang('Kind'), array(
                    $icon('bi-file-earmark-text'),
                    $span($report_samples[0]['kind'], '', lang('Kind'), $bind('kind')),
                )),
                $el('h3', 'h5 fw-bold mb-2', lang('Title'), array(), array_merge(array('text' => $report_samples[0]['title']), $bind('title'))),
                $para($report_samples[0]['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'pg-hide-if-empty mt-auto', lang('Download'), array(
                    $el('span', 'pg-field-value', lang('Value'), array(
                        $link(lang('Download'), '#', 'btn btn-sm btn-outline-primary rounded-pill px-3', array(),
                            array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => 'file'))),
                    )),
                )),
            )),
        )),
    ), '6', '4');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The coming events, then the sign-up form, which a Sign up button opens
// with its event filled in.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Runs, open evenings, collection days and talks: the coming dates and how to join us.'),
    'form'             => array(
        'form_name'            => lang('Event Sign-ups'),
        'confirmation_message' => lang('Thank you, you are signed up. We look forward to seeing you; we write to you if anything changes.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New sign-up: ^^event^^'),
    ),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Runs, open evenings, collection days and talks. Every event is free to join; signing up tells us how many to expect.')),
        $el('section', 'py-5', lang('Coming Events'), array(
            $container(array($widget('events_list'))),
        ), $searchable('coming-events', lang('events, run, open evening, collection, talk'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Sign Up'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Sign up for an event'), 'h4 fw-semibold mb-2'),
                                $para(lang('No ticket and no seat to choose: tell us who is coming, and we save you a place.'), 'text-body-secondary mb-4'),
                                $widget('event_signup_form'),
                            )),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        ), $searchable('sign-up', lang('sign up, join, register, attend'))),
    )),
);

// One event: the record the lists link to, open to anybody.
$event_pages[] = array(
    'key'     => 'event_view',
    'name'    => lang('event'),
    'folder'  => 'public',
    'title'   => lang('Event'),
    'search'  => false,
    'tree'    => $page(array(
        $widget('event_detail'),
        $shared('donate_band'),
    )),
);

// The staff's form for a new event: it is on the lists as soon as it is
// sent, and leaves them once its day has passed.
$event_pages[] = array(
    'key'     => 'event_new',
    'name'    => lang('new-event'),
    'folder'  => 'private',
    'title'   => lang('New Event'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        // The sample events are written in when the form is made, so the
        // lists do not open empty (pg_cf_seed_sample_records()).
        'sample_records'       => $event_samples,
        'share_image_field'    => 'image',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('The event appears on the events page and the home page until its day has passed. To change or remove one, use its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('event_form'))),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The staff's form for a new report: it is on the transparency page as soon
// as it is sent. The file goes to the public reports folder.
$event_pages[] = array(
    'key'     => 'report_new',
    'name'    => lang('new-report'),
    'folder'  => 'private',
    'title'   => lang('New Report'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Reports'),
        'collection'           => 1,
        'confirmation_message' => lang('The report is published.'),
        'confirmation_page_id' => '{{tab:transparency}}',
        'sample_records'       => $report_samples,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Report'), lang('Add an annual report, the audited accounts or an impact report with its file; it appears on the transparency page as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('report_form'))),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$event_missing = lang('This event could not be found. It may have passed or been moved.');

$event_widgets = array(
    'home_events' => $events_list('home', 3),
    'events_list' => $events_list('events', 12),

    // One event: its picture, kind, title and words, and beside them when
    // and where, with the Sign up button.
    'event_detail' => array(
        'page'   => 'event_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:event_new}}',
            'access_control'      => 'public',
            'not_found_message'   => $event_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                    $heading('h1', lang('Event not found'), 'h3 fw-semibold mt-3'),
                    $para($event_missing, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('All events'), '{{page:events}}', 'btn btn-primary rounded-pill px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Event'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All events'), '', lang('Link Text')),
                        ), array('href' => '{{page:events}}')),
                        $edit_button(),
                    )),
                    $row(array(
                        $col(array(
                            $optional_image('image', 'title', 'object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary mb-4',
                                $photo('event-open-evening', 1200, 675), $event_sample['title']),
                            $span($event_sample['kind'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Kind'), $bind('kind')),
                            $heading('h1', $event_sample['title'], 'display-6 fw-semibold mb-3', $bind('title')),
                            $el('div', 'pg-field-value lead text-body-secondary', lang('Summary'), array(), array_merge(
                                array('text' => $event_sample['summary']), $bind('summary'))),
                        ), '', '8'),
                        $col(array(
                            $el('div', 'card border-0 shadow-sm rounded-4', lang('Event Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('dl', 'small mb-4', lang('Details'), array(
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-calendar3', 'text-primary'), $span(lang('Date'), '', lang('Text')))),
                                        $el('dd', 'mb-3', lang('Date'), array(), array_merge(array('text' => $event_sample['event_date']), $bind('event_date'))),
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-clock', 'text-primary'), $span(lang('Starts at'), '', lang('Text')))),
                                        $el('dd', 'mb-3', lang('Time'), array(), array_merge(array('text' => $event_sample['event_time']), $bind('event_time'))),
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-geo-alt', 'text-primary'), $span(lang('Location'), '', lang('Text')))),
                                        $el('dd', 'mb-0', lang('Location'), array(), array_merge(array('text' => $event_sample['location']), $bind('location'))),
                                    )),
                                    // The events page's sign-up form, with
                                    // this event filled in (?event=).
                                    $link(lang('Sign up'), '{{page:events}}', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                        $page_link('events', array('event' => 'title'))),
                                    $para(lang('Free to join. Signing up tells us how many to expect.'), 'small text-body-secondary text-center mb-0'),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The staff page's events form: one event.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('ch-ev-title', lang('Title'), pg_cf_with_role($input('ch-ev-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('ch-ev-date', lang('Date'), $input('ch-ev-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('ch-ev-time', lang('Starts at'), $input('ch-ev-time', 'text', 'event_time', 'off', true, array(), array($attr('placeholder', '18:30'))))), '6'),
                $col(array($field('ch-ev-kind', lang('Kind'), pg_cf_with_role($select('ch-ev-kind', 'kind', lang('Choose…'), $event_kinds), 'category'))), '6'),
                $col(array($field('ch-ev-location', lang('Location'), $input('ch-ev-location', 'text', 'location', 'off', true, array(), array($attr('placeholder', lang('Online')))))), '6'),
            ), '3'),
            $field('ch-ev-summary', lang('Summary'), pg_cf_with_role($textarea('ch-ev-summary', 'summary', 3), 'description')),
            $field('ch-ev-image', lang('Image'), pg_cf_with_role($file('ch-ev-image', 'image', 'public', 'image/*'), 'media')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The sign-up: the event (filled in by the Sign up button), who is
    // coming and how many, the consent. No seat and no status: the staff
    // are e-mailed every sign-up.
    'event_signup_form' => array(
        'page'   => 'events',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('ch-su-event', lang('Event'), $input('ch-su-event', 'text', 'event', 'off', true, array('url_param' => 'event')), 'mb-3',
                lang('The Sign up button of an event fills this in.')),
            $row(array(
                $col(array($field('ch-su-first-name', lang('First Name'), $input('ch-su-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ch-su-last-name', lang('Last Name'), $input('ch-su-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ch-su-email', lang('Email'), $input('ch-su-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ch-su-people', lang('How many are coming?'),
                    $input('ch-su-people', 'number', 'people', 'off', true, array('kind' => 'number'), array($attr('min', '1'), $attr('max', '20'), $attr('step', '1'), $attr('value', '1'))))), '6'),
            ), '3'),
            $agree('ch-su-consent', 'consent', lang('I agree that the charity keeps these details to plan the event and write to me about it, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Sign Up'), 'btn btn-primary btn-lg rounded-pill px-4'),
            $loop(array()),
        )),
    ),

    // The staff page's reports form: one report with its file.
    'report_form' => array(
        'page'   => 'report_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('ch-rp-title', lang('Title'), pg_cf_with_role($input('ch-rp-title', 'text', 'title', 'off'), 'title')),
            $field('ch-rp-kind', lang('Kind'), pg_cf_with_role($select('ch-rp-kind', 'kind', lang('Choose…'), $report_kinds), 'category')),
            $field('ch-rp-summary', lang('Summary'), pg_cf_with_role($textarea('ch-rp-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One sentence on what the report covers.')),
            $field('ch-rp-file', lang('File'), $file('ch-rp-file', 'file', 'reports', '.pdf'), 'mb-3',
                lang('The report as a PDF; anybody can open it from the transparency page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The transparency page's reports, newest first.
    'reports' => array(
        'page'   => 'transparency',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:report_new}}',
            'items_per_page'      => 12,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('Our first reports are on their way.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($report_card()))), '4'),
        )),
    ),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
