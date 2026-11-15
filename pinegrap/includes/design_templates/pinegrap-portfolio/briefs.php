<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Web Designer Portfolio", its project briefs: the brief
 * form, the brief's own page with its messages, My Briefs and the staff's
 * list; the privacy page the consent boxes link, the brief's two e-mails,
 * the new message e-mail, the contact form's two e-mails and the
 * newsletter's welcome (the forms of standard.php name them), and the
 * widgets that draw them. Included
 * after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A brief is a record of the Project Briefs form, as a valuation is in the
 * Real Estate Agency template. A visitor without an account gets one
 * (auto-registration), so the brief's page and My Briefs show it at once.
 * Its Status is an office-use-only field the designer changes in the
 * control panel; the messages under it are the page's comments, mailed to
 * the client and to the designer.
 *
 * The Project type starts with the kind the page that sent the visitor
 * names (?type=, the kind's title: a service or a case study), the Package
 * with the package card's choice (?package=). The files a client sends
 * are kept in the template's private folder: the brief's page links them
 * for whoever may read the brief.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$brief_pages = array();

// The side column of the brief form: what happens next, then the ways to
// reach the designer directly.
$brief_aside = function () use ($el, $heading, $para, $link, $icon, $span, $designer) {
    $steps = array(
        lang('Your brief opens its own page, and an account for you if you have none.'),
        lang('Within two working days I answer there with questions and a first idea of the price.'),
        lang('After a short call you get a proposal with a fixed price and a timeline.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-bold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Get in Touch'), array(
            $heading('h2', lang('Rather talk first?'), 'h5 fw-bold mb-3'),
            $para($designer['available'], 'small text-body-secondary mb-3'),
            $el('p', 'd-flex align-items-center gap-2 small mb-2', lang('Email'), array(
                $icon('bi-envelope', 'text-primary'),
                $link($designer['email'], 'mailto:' . $designer['email'], 'link-body-emphasis'),
            )),
            $el('p', 'd-flex align-items-center gap-2 small mb-0', lang('Phone'), array(
                $icon('bi-telephone', 'text-primary'),
                $span($designer['phone'], '', lang('Phone')),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The project brief. What is sent opens the brief's own page.
$brief_pages[] = array(
    'key'              => 'brief',
    'name'             => lang('start-a-project'),
    'folder'           => 'public',
    'title'            => lang('Start a Project'),
    'meta_description' => lang('Tell me about your website, shop or brand in a few minutes; you get an answer and a first idea of the price within two working days.'),
    'form'             => array(
        'form_name'            => lang('Project Briefs'),
        'confirmation_message' => lang('Thank you, your brief has reached me. I answer within two working days on its page and by e-mail.'),
        'confirmation_page_id' => '{{tab:brief_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New project brief: ^^project_type^^'),
        'notify_page_id'       => '{{tab:email_brief_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('I received your project brief'),
        'confirm_page_id'      => '{{tab:email_brief_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Start a Project'),
            lang('A few questions about what you need, your budget and your timing. It takes five minutes, and nothing is binding.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('brief_form'))),
                        )),
                    ), '', '8'),
                    $col($brief_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One brief: what the client wrote, its status and the messages under it.
$brief_pages[] = array(
    'key'      => 'brief_view',
    'name'     => lang('project-brief'),
    'folder'   => 'registration',
    'title'    => lang('Project Brief'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your project'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('brief_detail'),
        $comments_section(lang('Messages'), 'brief_messages'),
    ), false),
);

// My Briefs: the client's briefs (for the staff, everybody's).
$brief_pages[] = array(
    'key'     => 'my_briefs',
    'name'    => lang('my-briefs'),
    'folder'  => 'registration',
    'title'   => lang('My Briefs'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Briefs'), lang('Your project briefs, where each one stands and my messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_briefs_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Brief'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span(lang('Another project?'), '', lang('Text')),
                            )),
                            $para(lang('Send a new brief; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start a Project'), '{{page:brief}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every brief, newest first, with a search.
$brief_pages[] = array(
    'key'     => 'staff_briefs',
    'name'    => lang('all-briefs'),
    'folder'  => 'private',
    'title'   => lang('All Briefs'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('All Briefs'), lang('Every project brief, newest first: set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_briefs_list'))),
        )),
    )),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$brief_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What I keep about you, why, and what you can ask of me.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What I keep about you, why, and what you can ask of me.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your business and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you send a project brief, sign up for the newsletter or open an account, I keep your name, your contact details, what you tell me about your project and the files you send, to answer you, prepare a proposal and work on your project.'), 'mb-3'),
                $para(lang('I keep it as long as the law requires, never sell it and never give it to advertisers. A project appears among my work only with the client\'s agreement. You can see and change your account data from My Account, and ask me about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the designer receives and the reply to the client, and
// the new message e-mail.
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_brief_new',
    'name'  => lang('email-new-brief'),
    'title' => lang('New Brief E-mail'),
    'tree'  => $mail_page(array($widget('email_brief_admin'))),
));
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_brief_received',
    'name'  => lang('email-brief-received'),
    'title' => lang('Brief Received E-mail'),
    'tree'  => $mail_page(array($widget('email_brief_client'))),
));
// A new message or reply: sent to the client of a brief, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your project brief, a conversation or a Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// The contact form's notification: what the designer receives for a
// message.
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
// The contact form's reply to the sender.
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_message_received',
    'name'  => lang('email-message-received'),
    'title' => lang('Message Received E-mail'),
    'tree'  => $mail_page(array($widget('email_message_sender'))),
));
// The newsletter's welcome, sent to a new subscriber.
$brief_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the newsletter')),
        $mail_para(lang('Thank you for subscribing. New case studies and Journal posts reach you here, a few times a month at most.')),
        $mail_para(lang('Changed your mind? Every e-mail I send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See my work'), '{{page:work}}'),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's name.
$brief_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A brief in a list: the kind of project, the status, the package, the
// budget and the date sent, linked to its page. The staff's row starts
// with the client's name and shows the phone; the client's shows the
// number of messages.
$brief_row = function ($staff = false) use ($el, $span, $icon, $bind, $brief_person, $category_titles, $package_choices, $budget_choices, $brief_statuses) {
    $item = function ($icon_name, $name, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name), $span($sample, '', $name, $bind($field))));
    };
    $items = array(
        $item('bi-box', lang('Package'), $package_choices[1], 'package'),
        $item('bi-wallet2', lang('Budget'), $budget_choices[1], 'budget'),
        $item('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($brief_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($category_titles[0], 'text-body-secondary fw-normal', lang('Project type'), $bind('project_type')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($category_titles[0], 'fw-semibold', lang('Project type'), $bind('project_type'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Project Brief'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($brief_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A detail of the brief's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$brief_detail_line = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$brief_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};
// A long text the brief carries, in a soft box that leaves when empty.
$brief_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};

$brief_missing = lang('This brief could not be found, or it is not yours to see.');
$sample_goals = lang('A new site for our bakery: the menu, opening hours and cake orders for birthdays, easy to update from a phone.');
$sample_references = 'https://example.com, https://example.org';

// The lines of a brief, for its e-mails.
$mail_brief = function () use ($mail_box, $mail_line, $span, $bind, $category_titles, $package_choices, $budget_choices, $start_choices, $page_count_choices, $brand_choices, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Project type'), array($span($category_titles[0], '', lang('Project type'), $bind('project_type')))),
        $mail_line(lang('Package'), array($span($package_choices[1], '', lang('Package'), $bind('package')))),
        $mail_line(lang('Budget'), array($span($budget_choices[1], '', lang('Budget'), $bind('budget')))),
        $mail_line(lang('When to start'), array($span($start_choices[1], '', lang('When to start'), $bind('start')))),
        $mail_line(lang('Deadline'), array($span('2027-03-01', '', lang('Deadline'), $bind('deadline')))),
        $mail_line(lang('Pages'), array($span($page_count_choices[1], '', lang('Pages'), $bind('pages')))),
        $mail_line(lang('Logo and colours'), array($span($brand_choices[0][1], '', lang('Logo and colours'), $bind('brand_assets')))),
        $mail_line(lang('Contact by'), array($span($contact_choices[0][1], '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Project Brief'));
};

// The account a form opened for a sender who had none, in the reply
// e-mails: the address it signs in with, and how to choose a password.
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
    ), array_merge($mail_style('border-top:1px solid #e4e4e7;padding-top:16px'), $show_when('has_new_account')));
};
// The samples of the contact form's e-mails (a topic as the contact form offers).
$sample_topic = lang('A question about a website');
$sample_message = lang('Hello, could you tell me how long a company site of about ten pages usually takes?');

// The form detail settings of a widget: the brief's page shows a brief
// only to its client (and the staff), an e-mail page the one just sent.
$brief_view_config = function ($page_key, $access, $missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:brief}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

// A list of briefs: the client's own (the staff see them all), or the
// staff's with a search.
$brief_list = function ($page_key, $row_node, $empty, $search = false) use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:brief}}',
        'detail_page_id'      => '{{tab:brief_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($search) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'project_type', 'package', 'budget', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by client, project type, package, budget or status...'),
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
            $el('div', 'list-group', lang('Brief List'), array($loop(array($row_node)))),
        )),
    );
};

$brief_widgets = array(

    // The brief: the fields are the page's form. The Project type and the
    // Package start with the choice of the page that sent the visitor
    // (?type=, ?package=). The Status is the designer's (office use only):
    // every brief starts as Brief received.
    'brief_form' => array(
        'page'   => 'brief',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The project')),
            $row(array(
                $col(array($field('pd-br-type', lang('Project type'), $select('pd-br-type', 'project_type', lang('Choose a kind of project…'), $category_titles, true, '', array('url_param' => 'type')))), '6'),
                $col(array($field('pd-br-package', lang('Package'), $select('pd-br-package', 'package', '', $package_choices, true, $package_choices[0], array('url_param' => 'package')))), '6'),
                $col(array($field('pd-br-pages', lang('Pages'), $select('pd-br-pages', 'pages', lang('Choose a size…'), $page_count_choices, false))), '6'),
            ), '3'),
            $radios('pd-br-brand', 'brand_assets', lang('Do you have a logo and colours?'), $brand_choices, $brand_choices[0][0]),
            $form_step(2, lang('Budget and timing')),
            $row(array(
                $col(array($field('pd-br-budget', lang('Budget'), $select('pd-br-budget', 'budget', lang('Choose a range…'), $budget_choices))), '6'),
                $col(array($field('pd-br-start', lang('When to start'), $select('pd-br-start', 'start', lang('Choose a start…'), $start_choices))), '6'),
                $col(array($field('pd-br-deadline', lang('Deadline'), $input('pd-br-deadline', 'date', 'deadline', 'off', false, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('Only if a launch or an event sets the date.'))), '6'),
            ), '3'),
            $form_step(3, lang('Your goals')),
            $field('pd-br-goals', lang('What should the new site do?'), $textarea('pd-br-goals', 'goals', 5), 'mb-3',
                lang('Who your visitors are, what they should find or do, and what is not working today.')),
            $field('pd-br-references', lang('Sites you like'), $textarea('pd-br-references', 'reference_sites', 2, false), 'mb-3',
                lang('Addresses of sites you like, yours or anyone\'s, and what you like about them.')),
            $field('pd-br-files', lang('Files'), $file('pd-br-files', 'files', 'private'), 'mb-3',
                lang('A logo, a plan, the words you have (PDF, Word or a picture). More can follow under your brief.')),
            $form_step(4, lang('How to reach you')),
            $radios('pd-br-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $row(array(
                $col(array($field('pd-br-first-name', lang('First Name'), $input('pd-br-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('pd-br-last-name', lang('Last Name'), $input('pd-br-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('pd-br-email', lang('Email'), $input('pd-br-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('pd-br-phone', lang('Phone'), $input('pd-br-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('pd-br-status', lang('Status'), $select('pd-br-status', 'status', '', $brief_statuses, false, $brief_statuses[0], array('office_use_only' => true))),
            $agree('pd-br-consent', 'consent', lang('I agree that these details are kept to answer me and prepare a proposal, as the privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Brief'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('An answer within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One brief: what the client wrote and its status; the messages (the
    // proposal among them) follow as comments.
    'brief_detail' => $brief_view_config('brief_view', 'submitter_only', $brief_missing) + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-file-earmark-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Brief not found'), 'h3 fw-semibold mt-3'),
                            $para($brief_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Briefs'), '{{page:my_briefs}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your brief has reached me. I answer within two working days; this page shows where it stands, and you can write to me below.'), '', lang('Text')),
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
                                    $span(lang('My Briefs'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_briefs}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Brief Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Project Brief'), 'pdp-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $category_titles[0], 'h3 fw-bold mb-0', $bind('project_type')),
                                        )),
                                        $span($brief_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $brief_detail_line(lang('Package'), '', lang('Package'), $brief_bound($package_choices[1], 'package')),
                                        $brief_detail_line(lang('Budget'), '', lang('Budget'), $brief_bound($budget_choices[1], 'budget')),
                                        $brief_detail_line(lang('When to start'), '', lang('When to start'), $brief_bound($start_choices[1], 'start')),
                                        $brief_detail_line(lang('Deadline'), '', lang('Deadline'), $brief_bound('2027-03-01', 'deadline')),
                                        $brief_detail_line(lang('Pages'), '', lang('Pages'), $brief_bound($page_count_choices[1], 'pages')),
                                        $brief_detail_line(lang('Logo and colours'), '', lang('Logo and colours'), $brief_bound($brand_choices[0][1], 'brand_assets')),
                                        $brief_detail_line(lang('Best way to reach you'), '', lang('Contact by'), $brief_bound($contact_choices[0][1], 'contact_method')),
                                        $brief_detail_line(lang('Client'), '', lang('Client'), $brief_person(), true),
                                        $brief_detail_line(lang('Files'), 'text-break', lang('Files'), array(
                                            $link('brief.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'files', 'text' => 'files'))),
                                        ), true),
                                        $brief_detail_line(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $brief_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $brief_text(lang('What should the new site do?'), 'goals', $sample_goals),
                            $brief_text(lang('Sites you like'), 'reference_sites', $sample_references),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('More files or a question? Write to me below; the proposal comes here too.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a brief: the page's own Comments widget, shown only
    // to whoever may read the brief above it. Oldest first, all on one page,
    // with a file each. No Reply, Share or follow box: the client is
    // e-mailed every answer and the designer every message.
    'brief_messages' => array(
        'page'   => 'brief_view',
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
            'empty'     => lang('No messages yet. Write to me here about your project.'),
            'heading'   => lang('Write to me'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write about your project.'),
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

    'my_briefs_list' => $brief_list('my_briefs', $brief_row(),
        lang('No briefs yet. Tell me about your project and it shows here.')),

    'staff_briefs_list' => $brief_list('staff_briefs', $brief_row(true), lang('No briefs yet.'), true),

    // The e-mail pages' widgets: drawn for the brief just sent.
    'email_brief_admin' => $brief_view_config('email_brief_new', 'public', lang('The brief could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new project brief')),
            $mail_para(lang('It came from the brief form. Answer on the brief\'s page within two working days and set its status there; what you write under it reaches the client by e-mail.')),
            $mail_brief(),
            $mail_box(array(
                $mail_line(lang('Client'), $brief_person()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('What should the new site do?'), array($span($sample_goals, '', lang('Text'), $bind('goals')))),
                $mail_line(lang('Sites you like'), array($span($sample_references, '', lang('Text'), $bind('reference_sites')))),
            ), lang('Client')),
            $mail_button(lang('Open the brief'), '{{page:brief_view}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#52525b;margin:24px 0 0')),
        )),
    ),
    'email_brief_client' => $brief_view_config('email_brief_received', 'public', lang('The brief could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('I received your project brief')),
            $mail_greeting(),
            $mail_para(lang('Thank you for telling me about your project. I read every brief myself and answer within two working days with my questions and a first idea of the price.')),
            $mail_brief(),
            $mail_para(lang('Something to add, or a file? Write to me on the brief\'s page; everything about your project stays there.')),
            $mail_button(lang('Open my brief'), '{{page:brief_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),

    // The contact form's e-mails: drawn for the message just sent.
    'email_message_admin' => array(
        'page'   => 'email_new_message',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new message has arrived')),
            $mail_para(lang('It came from the contact form. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
            $mail_box(array(
                $mail_line(lang('Sender'), $brief_person()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Company'), array($span('Harbour Roasters', '', lang('Company'), $bind('company')))),
                $mail_line(lang('Subject'), array($span($sample_topic, '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#52525b;margin:24px 0 0')),
        )),
    ),
    'email_message_sender' => array(
        'page'   => 'email_message_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('I have your message')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing. My answer will be on the conversation page and in your inbox, and you get an e-mail when it is there.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => $sample_topic, '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
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

return array('pages' => $brief_pages, 'widgets' => $brief_widgets);
