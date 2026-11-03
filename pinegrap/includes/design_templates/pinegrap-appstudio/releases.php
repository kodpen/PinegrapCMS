<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its release notes and beta: the
 * staff page that adds a release note (the Releases form, a collection,
 * with the sample notes written in when it is made), the What's New page
 * with its app filter, the home's newest notes, the beta sign-up, the
 * sign-up's own page and the staff's list of beta testers. Included after
 * parts.php in the same scope; returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * A release note is a record of the Releases form. Its App and Platform
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?app= (the app page's "What's new" link, the filter links above
 * the list) narrows the list by the name a visitor sees. The release date
 * is a date field the list sorts on, newest first.
 *
 * A beta sign-up is a record of the Beta Testers form. Its App starts
 * with the app the visitor came from (?app=); its Status is an
 * office-use-only field the developer changes in the control panel when
 * the TestFlight or Google Play invitation is sent, by hand. A visitor
 * without an account gets one, so the sign-up's page shows it at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$release_pages = array();

// The states of a beta sign-up, in the order it moves through them.
$beta_statuses = array(lang('Signed up'), lang('Invitation sent'), lang('Testing'), lang('Left the beta'));

// The sample beta sign-up the canvas shows.
$beta_sample = array(
    'app'      => $apps['stride']['name'],
    'platform' => $platforms['android']['title'],
    'device'   => 'Pixel 8',
    'os'       => 'Android 15',
    'note'     => lang('I run four times a week with a Wear OS watch and would like to try the new interval runs.'),
);

// ── Pieces ──────────────────────────────────────────────────────────────

// A list's filter on a field from a URL parameter: left out while the
// parameter is not in the address.
$rel_url_filter = function ($field, $param) {
    return array('field' => $field, 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => $param);
};

// A Releases list: newest release first. No detail page: a note is read
// whole in the list.
$rel_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:release_new}}',
        'order_by_field'      => 'release_date',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No release notes for this app yet. The first one comes with its next update.'),
    ), $more);
};
$rel_list_tree = function () use ($widget_root, $el, $loop, $release_card) {
    return $widget_root(array(
        $el('div', 'border-top', lang('Release Notes'), array($loop(array($release_card(0))))),
    ));
};

// The links above the list: every app, then one per app, each opening the
// What's New page with ?app= set to the app's name.
$rel_filter_bar = function () use ($el, $link, $span, $attr, $query, $app_names) {
    $children = array(
        $span(lang('App'), 'small fw-semibold text-body-secondary me-1', lang('Label')),
        $link(lang('All Apps'), '{{page:releases}}', 'btn btn-sm btn-outline-secondary rounded-pill'),
    );
    foreach ($app_names as $app_name) {
        $children[] = $link($app_name, '{{page:releases}}?' . $query(array('app' => $app_name)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children,
        array('_attrs' => array($attr('aria-label', lang('Filter by app')))));
};

// The beta box beside the notes and on the sign-up page: what a tester
// gets and the way to join.
$beta_box = function ($with_button) use ($el, $heading, $para, $link, $icon, $span, $check_list) {
    $children = array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
            $icon('bi-phone-vibrate', 'text-primary'),
            $span(lang('Try the next version first'), '', lang('Text')),
        )),
        $para(lang('Beta testers get each update a week or two before the stores, through TestFlight on iPhone and Google Play testing on Android.'), 'small text-body-secondary mb-3'),
        $check_list(array(lang('Free, and you can leave at any time'), lang('A short question after each beta'), lang('Your name in the release notes, if you like')), 'list-unstyled small mb-0'),
    );
    if ($with_button) {
        $children[] = $link(lang('Join the Beta'), '{{page:beta}}', 'btn btn-sm btn-primary rounded-pill px-3 mt-3');
    }
    return $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Beta'), $children);
};

// ── Pages ───────────────────────────────────────────────────────────────

// What's New: every release note, newest first; ?app= narrows it to one
// app (the app page's link, the filter links).
$release_pages[] = array(
    'key'              => 'releases',
    'name'             => lang('whats-new'),
    'folder'           => 'public',
    'title'            => lang('What\'s New'),
    'meta_description' => lang('Release notes of every app: what each update brings on iPhone and Android, newest first.'),
    'tree'             => $page(array(
        $page_header(lang('What\'s New'), lang('What each update brings, app by app, newest first. Choose an app to see only its notes.')),
        $el('section', 'py-5', lang('Release Notes'), array(
            $container(array(
                $row(array(
                    $col(array($rel_filter_bar(), $widget('releases_list')), '', '8'),
                    $col(array($beta_box(true)), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('release-notes', lang('changelog, update, version, new features, bug fixes'))),
        $shared('project_band'),
    )),
);

// The staff page that adds a release note. The form is the Releases
// collection; the sample notes are written in when the form is made, so
// What's New does not open empty (pg_cf_seed_sample_records()).
$release_samples = array();
foreach ($releases as $release_row) {
    $release_samples[] = array(
        'app'          => $apps[$release_row['app']]['name'],
        'version'      => $release_row['version'],
        'platform'     => $platform_title($release_row['platform']),
        'release_date' => $release_row['date'],
        'notes'        => $release_row['notes'],
    );
}
$release_pages[] = array(
    'key'     => 'release_new',
    'name'    => lang('new-release-note'),
    'folder'  => 'private',
    'title'   => lang('New Release Note'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Releases'),
        'collection'           => 1,
        'confirmation_message' => lang('The release note is published.'),
        'confirmation_page_id' => '{{tab:releases}}',
        'sample_records'       => $release_samples,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Release Note'), lang('Write what the update brings; it appears on What\'s New as soon as you send it. Change the app\'s own version on its Edit button too.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('release_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The beta sign-up. What is sent opens the sign-up's own page.
$release_pages[] = array(
    'key'              => 'beta',
    'name'             => lang('join-the-beta'),
    'folder'           => 'public',
    'title'            => lang('Join the Beta'),
    'meta_description' => lang('Test the next version of an app before it reaches the stores, on iPhone with TestFlight or on Android with Google Play testing.'),
    'form'             => array(
        'form_name'            => lang('Beta Testers'),
        'confirmation_message' => lang('Thank you for joining the beta. The invitation reaches your e-mail with the next beta, usually within a week.'),
        'confirmation_page_id' => '{{tab:beta_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New beta tester: ^^app^^'),
    ),
    'tree'             => $page(array(
        $page_header(lang('Join the Beta'), lang('Try the next version of an app before anyone else and tell me what you think. It is free, and you can leave at any time.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('beta_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $beta_box(false),
                        $el('div', 'p-4 rounded-4 border mt-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'h6 fw-bold mb-3'),
                            $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                                $step(1, lang('Your sign-up opens its own page'), lang('With an account for you if you have none.')),
                                $step(2, lang('The invitation comes by e-mail'), lang('From TestFlight or Google Play, with the next beta.')),
                                $step(3, lang('You try it and tell me'), lang('A short question after each beta; every answer is read.')),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('beta', lang('beta testing, TestFlight, early access, test version'))),
    ), false),
);

// One sign-up: the app, the phone, the status.
$release_pages[] = array(
    'key'     => 'beta_view',
    'name'    => lang('beta-sign-up'),
    'folder'  => 'registration',
    'title'   => lang('Beta Sign-up'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('beta_detail')), false),
);

// The staff's list of beta testers, newest first, with a search.
$release_pages[] = array(
    'key'     => 'staff_beta',
    'name'    => lang('beta-testers'),
    'folder'  => 'private',
    'title'   => lang('Beta Testers'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Beta Testers'), lang('Everyone who joined a beta, newest first. Send the invitation from TestFlight or the Play Console, then set the status.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_beta_list'))),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// The tester's name.
$beta_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A detail of the sign-up's card: the label, then the value.
$beta_detail_row = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$beta_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A sign-up in the staff's list: the tester, the app, the phone, linked to
// the sign-up's page.
$beta_row = function () use ($el, $span, $icon, $bind, $beta_name, $beta_sample, $beta_statuses) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array($icon($icon_name), $span($sample, '', $label, $bind($field))));
    };
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Beta Tester'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $el('span', 'fw-semibold', lang('Tester'), array_merge($beta_name(), array(
                $span(' · ', 'text-body-secondary', lang('Text')),
                $span($beta_sample['app'], 'text-body-secondary fw-normal', lang('App'), $bind('app')),
            ))),
            $span($beta_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
            $item('bi-phone', lang('Platform'), $beta_sample['platform'], 'platform'),
            $item('bi-cpu', lang('Phone model'), $beta_sample['device'], 'device'),
            $item('bi-envelope', lang('Email'), 'jane.cooper@example.com', 'email'),
            $item('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        )),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

$beta_missing = lang('This beta sign-up could not be found, or it is not yours to see.');

$release_widgets = array(

    // The staff's Releases form: one note. The App starts with the app the
    // address names (?app=), the Platform with both.
    'release_form' => array(
        'page'   => 'release_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('ma-rel-app', lang('App'), pg_cf_with_role($select('ma-rel-app', 'app', lang('Choose an app…'), $app_names, true, '', array('url_param' => 'app')), 'title'))), '6'),
                $col(array($field('ma-rel-version', lang('Version'), $input('ma-rel-version', 'text', 'version', 'off', true, array(), array($attr('placeholder', '2.5.0'))), 'mb-3',
                    lang('As the stores show it, for example 2.5.0.'))), '6'),
                $col(array($field('ma-rel-platform', lang('Platform'), pg_cf_with_role($select('ma-rel-platform', 'platform', '', $platform_choices, true, $platform_both), 'category'))), '6'),
                $col(array($field('ma-rel-date', lang('Release date'), $input('ma-rel-date', 'date', 'release_date', 'off'))), '6'),
            ), '3'),
            $field('ma-rel-notes', lang('What changed'), pg_cf_with_role($textarea('ma-rel-notes', 'notes', 5), 'description'), 'mb-3',
                lang('In the words of the store\'s "What\'s New": what users notice first, then the fixes.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // What's New: every note, twenty to a page; ?app= narrows it.
    'releases_list' => array(
        'page'   => 'releases',
        'slug'   => lang('form-list'),
        'config' => $rel_list_config(array(
            'items_per_page' => 20,
            'filters'        => array($rel_url_filter('app', 'app')),
        )),
        'tree'   => $rel_list_tree(),
    ),

    // The home page's three newest notes.
    'home_releases' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $rel_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'empty_message'  => lang('No release notes yet.'),
        )),
        'tree'   => $rel_list_tree(),
    ),

    // The beta sign-up: the fields are the page's form. The App starts
    // with the app the visitor came from (?app=). The Status is the
    // developer's (office use only): every sign-up starts as Signed up.
    'beta_form' => array(
        'page'   => 'beta',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The app and your phone')),
            $row(array(
                $col(array($field('ma-beta-app', lang('App'), $select('ma-beta-app', 'app', lang('Choose an app…'), $app_names, true, '', array('url_param' => 'app')))), '6'),
                $col(array($field('ma-beta-platform', lang('Platform'), $select('ma-beta-platform', 'platform', lang('Choose a platform…'), array($platforms['ios']['title'], $platforms['android']['title'])))), '6'),
                $col(array($field('ma-beta-device', lang('Phone model'), $input('ma-beta-device', 'text', 'device', 'off', true, array(), array($attr('placeholder', 'iPhone 15, Pixel 8'))))), '6'),
                $col(array($field('ma-beta-os', lang('System version'), $input('ma-beta-os', 'text', 'os_version', 'off', false, array(), array($attr('placeholder', 'iOS 18, Android 15'))), 'mb-3',
                    lang('In Settings, under About.'))), '6'),
            ), '3'),
            $field('ma-beta-note', lang('What would you like to test?'), $textarea('ma-beta-note', 'note', 3, false), 'mb-3',
                lang('How you use the app, a watch you pair with it, or a feature you are waiting for.')),
            $form_step(2, lang('Where to send the invitation')),
            $row(array(
                $col(array($field('ma-beta-first-name', lang('First Name'), $input('ma-beta-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ma-beta-last-name', lang('Last Name'), $input('ma-beta-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            ), '3'),
            $field('ma-beta-email', lang('Email'), $input('ma-beta-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')), 'mb-3',
                lang('The address of your Apple ID or Google account: the invitation can only be accepted there.')),
            $field('ma-beta-status', lang('Status'), $select('ma-beta-status', 'status', '', $beta_statuses, false, $beta_statuses[0], array('office_use_only' => true))),
            $agree('ma-beta-consent', 'consent', lang('I agree that the developer keeps these details to send me beta invitations and ask about them, as the privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Join the Beta'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The invitation comes with the next beta.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One sign-up: the app, the phone and its status, for the tester (and
    // the developer).
    'beta_detail' => array(
        'page'   => 'beta_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:beta}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $beta_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-question-circle', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Sign-up not found'), 'h3 fw-semibold mt-3'),
                            $para($beta_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Join the Beta'), '{{page:beta}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you for joining the beta. The invitation reaches your e-mail with the next beta, usually within a week; this page shows where your sign-up stands.'), '', lang('Text')),
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
                                    $span(lang('What\'s New'), '', lang('Link Text')),
                                ), array('href' => '{{page:releases}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Sign-up Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Beta Sign-up'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $beta_sample['app'], 'h3 fw-semibold mb-0', $bind('app')),
                                        )),
                                        $span($beta_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $beta_detail_row(lang('Platform'), '', lang('Platform'), $beta_bound($beta_sample['platform'], 'platform')),
                                        $beta_detail_row(lang('Phone model'), '', lang('Phone model'), $beta_bound($beta_sample['device'], 'device')),
                                        $beta_detail_row(lang('System version'), '', lang('System version'), $beta_bound($beta_sample['os'], 'os_version')),
                                        $beta_detail_row(lang('Tester'), '', lang('Tester'), $beta_name(), true),
                                        $beta_detail_row(lang('Email'), 'text-break', lang('Email'), $beta_bound('jane.cooper@example.com', 'email')),
                                        $beta_detail_row(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $beta_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('What would you like to test?'), array(
                                $para(lang('What would you like to test?'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $beta_bound($beta_sample['note'], 'note')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-info-circle', 'text-primary mt-1'),
                                $span(lang('No invitation after a week? Look in your spam folder, then write to me from the contact page with your reference code.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The staff's list of every sign-up, newest first, with a search.
    'staff_beta_list' => array(
        'page'   => 'staff_beta',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:beta}}',
            'detail_page_id'      => '{{tab:beta_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'email', 'app', 'platform', 'device', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by tester, app, phone or status...'),
            'empty_message'       => lang('No beta testers yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Beta Testers'), array($loop(array($beta_row())))),
        )),
    ),
);

return array('pages' => $release_pages, 'widgets' => $release_widgets);
