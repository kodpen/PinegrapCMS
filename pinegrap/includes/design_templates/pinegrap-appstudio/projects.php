<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its client projects: the project
 * request (hire), the request's own page with its messages, My Projects,
 * the staff's list, the e-mails the request sends and the widgets that draw
 * them. Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A project request is a record of the Project Requests form, as a
 * valuation is in the Real Estate template. A visitor without an account
 * gets one (auto-registration), so the request's page and My Projects show
 * it at once. Its Status is an office-use-only field the developer changes
 * in the control panel; the messages under it are the page's comments,
 * mailed to the client and to the developer.
 *
 * The form's kind of app starts with the service the visitor came from
 * (?type=, the kind's words) and its Package with the package card's choice
 * (?package=, the package's name). The specs and sketches a client sends are
 * kept in the template's private folder: the record's page links them for
 * whoever may read the record.
 *
 * Every helper of this file is named $project_…, so it never takes the name
 * of a helper of another page file included in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$project_pages = array();

$project_missing = lang('This project request could not be found, or it is not yours to see.');
$project_sample_type = $project_types['both'];
$project_sample_package = $packages['mvp']['title'];
$project_sample_about = lang('A booking app for our three yoga studios: class times, a pass that counts down and a reminder before each class.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the request page: what happens next, then the
// developer's availability and address.
$project_aside = function () use ($el, $heading, $para, $link, $icon, $span, $developer) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('Within two working days I answer there with questions or a first call.'),
        lang('You get a plan with a fixed price on the same page, and the messages stay with it.'),
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
        $el('div', 'p-4 rounded-4 border', lang('Availability'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-calendar-check', 'text-primary'),
                $span($developer['available'], '', lang('Text')),
            )),
            $para(lang('Rather talk first? Write or call; the address and phone are below.'), 'small text-body-secondary mb-2'),
            $el('p', 'small mb-1', lang('Email'), array(
                $link($developer['email'], 'mailto:' . $developer['email'], 'link-primary text-decoration-none'),
            )),
            $el('p', 'small mb-0', lang('Phone'), array(
                $link($developer['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $developer['phone']), 'link-primary text-decoration-none'),
            )),
        )),
    );
};

// A My list page: the header, the list, and a card that starts a new
// project.
$project_my_page = function () use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header(lang('My Projects'), lang('Your project requests, their status and the developer\'s messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_projects_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-phone', 'text-primary'),
                                $span(lang('Another app in mind?'), '', lang('Text')),
                            )),
                            $para(lang('Tell me about it; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// ── Pages ───────────────────────────────────────────────────────────────

// The project request. What is sent opens the request's own page.
$project_pages[] = array(
    'key'              => 'hire',
    'name'             => lang('start-a-project'),
    'folder'           => 'public',
    'title'            => lang('Start a Project'),
    'meta_description' => lang('Tell me about the app you want built: what it is for, the platforms, the budget and when to start. I answer within two working days.'),
    'form'             => array(
        'form_name'            => lang('Project Requests'),
        'confirmation_message' => lang('Thank you, your project request has reached me. I answer on its page within two working days.'),
        'confirmation_page_id' => '{{tab:project_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New project request: ^^project_type^^'),
        'notify_page_id'       => '{{tab:email_project_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('I received your project request'),
        'confirm_page_id'      => '{{tab:email_project_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Start a Project'),
            lang('Tell me about the app: who it is for, what it must do first and when you would like it in the stores.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('project_form'))),
                        )),
                    ), '', '8'),
                    $col($project_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('project-request', lang('hire, quote, build my app, app developer, freelance, price'))),
    ), false),
);

// One project request: the request, its status and the messages under it.
$project_pages[] = array(
    'key'      => 'project_view',
    'name'     => lang('project'),
    'folder'   => 'registration',
    'title'    => lang('Project'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your project'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('project_detail'),
        $comments_section(lang('Messages'), 'project_messages'),
    ), false),
);

// My Projects: the client's project requests (for the staff, everybody's).
$project_pages[] = array(
    'key'     => 'my_projects',
    'name'    => lang('my-projects'),
    'folder'  => 'registration',
    'title'   => lang('My Projects'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $project_my_page(),
);

// The staff's list of every project request, newest first, with a search.
$project_pages[] = array(
    'key'     => 'staff_projects',
    'name'    => lang('all-project-requests'),
    'folder'  => 'private',
    'title'   => lang('Project Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Project Requests'), lang('Every project request, newest first: send the proposal, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_projects_list'))),
        )),
    )),
);

// The e-mails: what the developer receives and the reply to the client.
$project_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_project_new',
    'name'  => lang('email-new-project'),
    'title' => lang('New Project E-mail'),
    'tree'  => $mail_page(array($widget('email_project_admin'))),
));
$project_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_project_received',
    'name'  => lang('email-project-received'),
    'title' => lang('Project Received E-mail'),
    'tree'  => $mail_page(array($widget('email_project_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's name.
$project_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A project request in a list: the kind of app, the status, the details,
// linked to its page. The staff's row starts with the client and adds the
// phone; the client's counts the messages.
$project_row = function ($staff = false) use ($el, $span, $icon, $bind, $project_person, $project_sample_type, $project_sample_package, $budget_choices, $start_choices) {
    $items = array();
    foreach (array(
        array('bi-box-seam', lang('Package'), $project_sample_package, 'package'),
        array('bi-wallet2', lang('Budget'), $budget_choices[2], 'budget'),
        array('bi-flag', lang('Start'), $start_choices[1], 'start'),
    ) as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($project_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($project_sample_type, 'text-body-secondary fw-normal', lang('Project Type'), $bind('project_type')),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($project_sample_type, 'fw-semibold', lang('Project Type'), $bind('project_type'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Project'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Request received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A list of project requests: the client's own (the staff see them all), or
// the staff's with a search.
$project_list = function ($page_key, $staff) use ($widget_root, $el, $loop, $project_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:hire}}',
        'detail_page_id'      => '{{tab:project_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'company', 'project_type', 'package', 'budget', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by client, company, kind of app, package, budget or status...'),
        );
        $config['empty_message'] = lang('No project requests yet.');
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
        $config['empty_message'] = lang('No project requests yet. Tell me about the app you want built.');
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($project_row($staff))))),
        )),
    );
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$project_detail_line = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$project_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The request's page: the not-found notice; for the record, the sent
// notice, the new account notice, the way back and the Edit button, the
// card with the details, what the client wrote and where to write next.
$project_view_tree = function () use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button,
                                      $project_missing, $project_detail_line, $project_bound, $project_person, $project_sample_type,
                                      $project_sample_package, $project_sample_about, $budget_choices, $start_choices, $design_choices) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-phone-vibrate', 'display-5 text-body-tertiary'),
                        $heading('h2', lang('Project request not found'), 'h3 fw-semibold mt-3'),
                        $para($project_missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link(lang('My Projects'), '{{page:my_projects}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span(lang('Thank you, your request has reached me. I answer here within two working days; this page shows the status, and you can write to me below.'), '', lang('Text')),
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
                                $span(lang('My Projects'), '', lang('Link Text')),
                            ), array('href' => '{{page:my_projects}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Project Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para(lang('Project request'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $project_sample_type, 'h3 fw-semibold mb-0', $bind('project_type')),
                                    )),
                                    $span(lang('Request received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                    $project_detail_line(lang('App name'), '', lang('App name'), $project_bound(lang('Studio Pass'), 'app_name')),
                                    $project_detail_line(lang('Package'), '', lang('Package'), $project_bound($project_sample_package, 'package')),
                                    $project_detail_line(lang('Designs'), '', lang('Designs'), $project_bound($design_choices[1][0], 'design')),
                                    $project_detail_line(lang('Budget'), '', lang('Budget'), $project_bound($budget_choices[2], 'budget')),
                                    $project_detail_line(lang('Start'), '', lang('Start'), $project_bound($start_choices[1], 'start')),
                                    $project_detail_line(lang('Best way to reach you'), '', lang('Contact by'), $project_bound(lang('E-mail'), 'contact_method')),
                                    $project_detail_line(lang('Client'), '', lang('Client'), $project_person(), true),
                                    $project_detail_line(lang('Company'), '', lang('Company'), $project_bound(lang('Sample Company'), 'company')),
                                    $project_detail_line(lang('Specs or sketches'), 'text-break', lang('Documents'), array(
                                        $link('app-brief.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                            '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                                    ), true),
                                    $project_detail_line(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $project_bound('ABC123', 'reference_code'))
                                )),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('About the app'), array(
                            $para(lang('About the app'), 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $project_sample_about), $bind('about'))),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span(lang('More details, a design file or a question? Write below; the proposal comes here too.'), '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// ── E-mail pieces ───────────────────────────────────────────────────────

// The request's lines, for both e-mails.
$project_mail_lines = function () use ($mail_box, $mail_line, $span, $bind, $project_sample_type, $project_sample_package, $budget_choices, $start_choices) {
    return $mail_box(array(
        $mail_line(lang('Kind of app'), array($span($project_sample_type, '', lang('Project Type'), $bind('project_type')))),
        $mail_line(lang('App name'), array($span(lang('Studio Pass'), '', lang('App name'), $bind('app_name')))),
        $mail_line(lang('Package'), array($span($project_sample_package, '', lang('Package'), $bind('package')))),
        $mail_line(lang('Budget'), array($span($budget_choices[2], '', lang('Budget'), $bind('budget')))),
        $mail_line(lang('Start'), array($span($start_choices[1], '', lang('Start'), $bind('start')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Project'));
};
// The client's lines of the developer's e-mail, with what they wrote.
$project_mail_client = function () use ($mail_box, $mail_line, $span, $bind, $project_person, $project_sample_about) {
    return $mail_box(array(
        $mail_line(lang('Client'), $project_person()),
        $mail_line(lang('Company'), array($span(lang('Sample Company'), '', lang('Company'), $bind('company')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('About the app'), array($span($project_sample_about, '', lang('Text'), $bind('about')))),
    ), lang('Client'));
};
// The reference code under the developer's e-mail.
$project_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account the form opened for a client who had none, in the reply.
$project_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #ddd6fe;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of an e-mail page's widget: drawn for the
// submission just sent.
$project_mail_detail = function ($page_key) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:hire}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The project request could not be found.'),
        ),
    );
};

$project_widgets = array(

    // The project request: the fields are the page's form. The kind of app
    // starts with the service the visitor came from (?type=), the Package
    // with the package card's choice (?package=). The Status is the
    // developer's (office use only): every request starts as received.
    'project_form' => array(
        'page'   => 'hire',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The app')),
            $row(array(
                $col(array($field('ma-pj-type', lang('Kind of app'), $select('ma-pj-type', 'project_type', lang('Choose a kind of app…'),
                    $project_type_choices, true, '', array('url_param' => 'type')))), '6'),
                $col(array($field('ma-pj-package', lang('Package'), $select('ma-pj-package', 'package', '',
                    $package_choices, true, $package_choices[0], array('url_param' => 'package')))), '6'),
            ), '3'),
            $field('ma-pj-name', lang('App name'), $input('ma-pj-name', 'text', 'app_name', 'off', false), 'mb-3',
                lang('A working name is enough; leave it empty if there is none yet.')),
            $field('ma-pj-about', lang('About the app'), $textarea('ma-pj-about', 'about', 5), 'mb-3',
                lang('Who it is for, what it must do first, and any app you like the feel of.')),
            $radios('ma-pj-design', 'design', lang('Are the screens designed?'), $design_choices, $design_choices[0][0]),
            $row(array(
                $col(array($field('ma-pj-budget', lang('Budget'), $select('ma-pj-budget', 'budget', lang('Choose a budget…'), $budget_choices))), '6'),
                $col(array($field('ma-pj-start', lang('Start'), $select('ma-pj-start', 'start', lang('Choose when…'), $start_choices))), '6'),
            ), '3'),
            $field('ma-pj-files', lang('Specs or sketches'), $file('ma-pj-files', 'documents', 'private'), 'mb-3',
                lang('A spec, a sketch or a screenshot (PDF, Word, text or a picture). More can follow under your request.')),
            $form_step(2, lang('How to reach you')),
            $radios('ma-pj-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $row(array(
                $col(array($field('ma-pj-first-name', lang('First Name'), $input('ma-pj-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ma-pj-last-name', lang('Last Name'), $input('ma-pj-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ma-pj-email', lang('Email'), $input('ma-pj-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ma-pj-phone', lang('Phone'), $input('ma-pj-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
                $col(array($field('ma-pj-company', lang('Company'), $input('ma-pj-company', 'text', 'company', 'organization', false, array('contact_field' => 'company')))), '12'),
            ), '3'),
            $field('ma-pj-status', lang('Status'), $select('ma-pj-status', 'status', '', $project_statuses, false, $project_statuses[0], array('office_use_only' => true))),
            $agree('ma-pj-consent', 'consent', lang('I agree that the developer keeps these details to answer my request and prepare a proposal, as the privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('I answer within two working days.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ),

    // One project request: the request, its status and what the client
    // wrote; the messages (the proposal among them) follow as comments.
    'project_detail' => array(
        'page'   => 'project_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:hire}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $project_missing,
        ),
        'tree'   => $project_view_tree(),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page, with a file each. No Reply, Share or follow box: the client
    // is e-mailed every answer and the developer every message.
    'project_messages' => array(
        'page'   => 'project_view',
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
            'empty'     => lang('No messages yet. Write to the developer here about your project.'),
            'heading'   => lang('Write to the developer'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to the developer.'),
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

    'my_projects_list' => $project_list('my_projects', false),

    'staff_projects_list' => $project_list('staff_projects', true),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_project_admin' => $project_mail_detail('email_project_new') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new project request')),
            $mail_para(lang('It came from the project form. Answer on the request\'s page with your questions or a time for a call, then set the status; what you write there reaches the client by e-mail.')),
            $project_mail_lines(),
            $project_mail_client(),
            $mail_button(lang('Open the request'), '{{page:project_view}}?r=^^reference_code^^'),
            $project_mail_reference(),
        )),
    ),
    'email_project_client' => $project_mail_detail('email_project_received') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('I received your project request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of me for your app. I read every request myself and answer within two working days, with questions or a time for a first call.')),
            $project_mail_lines(),
            $mail_para(lang('A design file or another detail? Add it on the request\'s page; the proposal comes there too.')),
            $mail_button(lang('Open my request'), '{{page:project_view}}?r=^^reference_code^^'),
            $project_mail_new_account(),
        )),
    ),
);

return array('pages' => $project_pages, 'widgets' => $project_widgets);
