<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", the account and staff
 * part: signing in, up and out, My Account and its pages, the staff
 * directory, the staff area (the newest project enquiries and consultation
 * requests, the Journal's form with its first articles, the directory's
 * form), the account e-mails, and the widgets that draw them (the account
 * bar's login region and My Account included). Included after parts.php,
 * mail.php, pages_public.php, projects.php, enquiries.php, careers.php and
 * standard.php in the same scope (the staff area's lists are enquiries.php's
 * staff lists); returns array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The control panel's address for the staff area's button, and whether the
// shop is on (the address book is a shop page).
$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$shop      = defined('ECOMMERCE') && ECOMMERCE;

$account_pages = array();

// ── Signing in and out ──────────────────────────────────────────────────

// A page that only places its widget, in the public folder unless it says
// otherwise. No enquiry band: these pages are a step on the way somewhere.
$sign_page = function ($key, $name, $title, $widget_key, $folder = 'public') use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => $folder,
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key)), false),
    );
};
$account_pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$account_pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$account_pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$account_pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$account_pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// ── My account ──────────────────────────────────────────────────────────

// The client's own pages, behind the registration folder: the overview with
// the invoices and the security settings under it, then the profile, the
// password, the e-mail preferences and, with the shop, the address book.
$account_pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security')), false),
);
$account_pages[] = $sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile', 'registration');
$account_pages[] = $sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password', 'registration');
$account_pages[] = $sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences', 'registration');
$account_pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book', 'registration'),
    array('requires' => 'ecommerce'));

// The studio's people as signed-in clients see them: the staff's own list,
// kept from the staff area, with the e-mail and the phone the public team
// page does not show.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The architects, the interior designers and the studio office, and how to reach them about your project.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the newest project enquiries and consultation
// requests first, then the staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 border pai-lift text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-semibold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '4');
};
$account_pages[] = array(
    'key'     => 'staff',
    'name'    => lang('staff'),
    'folder'  => 'private',
    'title'   => lang('Staff Area'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('Staff only'), 'badge rounded-0 text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'pai-display display-6 mb-3'),
                        $para(lang('The project enquiries, the consultation requests, the job applications and the conversations are kept here. Open a request, set its status and write under it; what you write reaches the client by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary rounded-0 px-4'),
                            $link(lang('All Project Enquiries'), '{{page:staff_enquiries}}', 'btn btn-secondary rounded-0 px-4'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Project Enquiries'), array(
            $container(array(
                $section_header(lang('Newest first'), lang('Project Enquiries'), lang('All Project Enquiries'), '{{page:staff_enquiries}}'),
                $widget('staff_enquiries_new'),
            )),
        )),
        $el('section', 'pb-5', lang('Consultation Requests'), array(
            $container(array(
                $section_header(lang('Newest first'), lang('Consultation Requests'), lang('All Consultation Requests'), '{{page:staff_consultations}}'),
                $widget('staff_consultations_new'),
            )),
        )),
        $el('section', 'pb-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-buildings', lang('New Project'), lang('Add a finished or running project to the portfolio, with its photographs and credits.'), '{{page:project_new}}'),
                    $staff_shortcut('bi-briefcase', lang('New Job Opening'), lang('Publish a job opening on the careers page.'), '{{page:job_new}}'),
                    $staff_shortcut('bi-person-workspace', lang('Job Applications'), lang('Every application from the careers page, with its portfolio and CV. Answer below each one.'), '{{page:staff_applications}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-journal-plus', lang('New Article'), lang('Write an article; it appears in the Journal as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory clients see.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary rounded-0 d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The Journal's first articles, written into its form when the form is
// made: the template carries them, so they are there on every site. Each
// is edited or deleted like any other article.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('What to ask before you buy a plot to build on'),
        'summary'     => lang('The zoning, the ground and the sun: three questions to answer before you sign for a plot.'),
        'content'     => $post_body(array(
            lang('Ask the municipality what the zoning plan allows: the height, the distance to the neighbours and the share of the plot you may build on.'),
            lang('Have the ground surveyed: rock, clay or fill changes the foundations, and the foundations change the budget.'),
            lang('Visit the plot in the morning and in the evening: where the sun comes from decides where the rooms you live in will be.'),
        )),
        'cover_image' => $photo('post-1', 1200, 675),
    ),
    array(
        'title'       => lang('Keeping the old walls: notes on adaptive reuse'),
        'summary'     => lang('Why a warehouse, a school or a factory is often a better start than an empty plot, and what it asks of the design.'),
        'content'     => $post_body(array(
            lang('An old building has already paid the carbon of its walls and its frame; keeping them is the greenest thing a project can do.'),
            lang('We survey every wall and beam before drawing anything, and let what we find lead the plan rather than fight it.'),
            lang('New parts are made to read as new: a clear line between what was there and what we added keeps both honest.'),
        )),
        'cover_image' => $photo('post-2', 1200, 675),
    ),
    array(
        'title'       => lang('Choosing materials that age well'),
        'summary'     => lang('Stone, timber, lime and brass: materials that look better after twenty years of use, and how we put them together.'),
        'content'     => $post_body(array(
            lang('We prefer materials that wear in rather than wear out: a timber floor that darkens, a brass handle polished by hands.'),
            lang('Local stone and lime plaster suit the climate, are repaired by local craftsmen and need no special care.'),
            lang('Every sample is seen in daylight on the site itself before it is chosen; a showroom light flatters everything.'),
        )),
        'cover_image' => $photo('post-3', 1200, 675),
    ),
    array(
        'title'       => lang('From the first sketch to the keys: a project\'s stages'),
        'summary'     => lang('Brief, concept, permit, drawings and the site: what happens at each stage and what we need from you.'),
        'content'     => $post_body(array(
            lang('We start by listening: the brief sets out how you live or work, the budget and the time you have.'),
            lang('The concept and the developed design come next, then the permit drawings and the detailed drawings the builder prices.'),
            lang('On site we visit every week, answer the builder\'s questions and walk the finished building with you before the handover.'),
        )),
        'cover_image' => $photo('post-4', 1200, 675),
    ),
);

// The staff's forms: the Journal and the staff directory.
$staff_form_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'form'    => $form,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'p-4 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$account_pages[] = $staff_form_page('blog_new', lang('new-article'), lang('New Article'),
    lang('Write the article; it appears in the Journal as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Journal'),
        'collection'           => 1,
        'confirmation_message' => lang('The article is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample articles are written in when the form is
        // made, so the Journal and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
        // The cover is the articles' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$account_pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in clients see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'collection'           => 1,
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// ── Account e-mails ─────────────────────────────────────────────────────

// The welcome after a sign-up and the password reset link. Their Account
// E-mail widgets fill in the member's name and links.
$account_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_account_welcome',
    'name'  => lang('email-account-welcome'),
    'title' => lang('Account Welcome E-mail'),
    'tree'  => $mail_page(array($widget('account_email_welcome'))),
));
$account_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_password_reset',
    'name'  => lang('email-password-reset'),
    'title' => lang('Password Reset E-mail'),
    'tree'  => $mail_page(array($widget('account_email_reset'))),
));
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$mail_account_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};

// ── Widget parts ────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// A link of the account menu; $flag shows it only while that widget flag
// is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};

// The client's account menu: the enquiries, the consultations and the job
// applications first.
$account_links = array(
    $account_link('bi-folder2-open', lang('My Enquiries'), '{{page:my_enquiries}}'),
    $account_link('bi-calendar2-check', lang('My Consultations'), '{{page:my_consultations}}'),
    $account_link('bi-person-workspace', lang('My Applications'), '{{page:my_applications}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// A card of the account overview: an icon, a title and a line, and its
// buttons.
$account_card = function ($icon_name, $name, $title, $text, $buttons) use ($el, $heading, $para, $icon) {
    return $el('div', 'p-4 border mb-4', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $icon($icon_name, 'fs-2 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pai-display h5 mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        )),
    ));
};

// The staff area's newest requests: the enquiries part's staff list on
// this page, the last ten without the search.
$staff_newest = function ($list) {
    $list['page'] = 'staff';
    $list['config']['items_per_page'] = 10;
    unset($list['config']['search_enabled'], $list['config']['search_fields'],
        $list['config']['search_width'], $list['config']['search_label']);
    return $list;
};
$staff_enquiries_new = $staff_newest($request_list('staff_enquiries', 'enquiry', 'enquiry_view', true,
    lang('No project enquiries yet.'), array(), '', $enquiry_row(true)));
$staff_consultations_new = $staff_newest($request_list('staff_consultations', 'consultation', 'consultation_view', true,
    lang('No consultation requests have been sent yet.'), array(), '', $consultation_row(true)));

// ── Widgets ─────────────────────────────────────────────────────────────

$account_widgets = array(

    // The account bar's view of the session: the sign-in buttons, or the
    // member's menu.
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
            'staff_page_id'      => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-1 gap-sm-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary rounded-0 text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 p-0 border-0 link-body-emphasis text-decoration-none text-nowrap dropdown-toggle', lang('Account Button'), array(
                    $image($portrait(lang('Jane Cooper'), 64), lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end rounded-0 shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Enquiries'), '{{page:my_enquiries}}'),
                    $login_page_item(lang('My Consultations'), '{{page:my_consultations}}'),
                    $login_page_item(lang('My Applications'), '{{page:my_applications}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Staff area'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // My Account: the member's card and menu beside the cards of the
    // enquiries, the consultations and the applications. No orders: the
    // studio sells nothing online.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary border mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'pai-display h4 mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary rounded-0', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group list-group-flush border-top border-bottom', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card('bi-folder2-open', lang('Enquiries Card'), lang('Your projects with us'),
                            lang('Follow your project enquiries, send drawings and photos of the site and read your architect\'s messages.'), array(
                                $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-0 px-4'),
                                $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-outline-primary rounded-0 px-4'),
                            )),
                        $account_card('bi-calendar2-check', lang('Consultations Card'), lang('Your consultations'),
                            lang('See the time the studio proposed for each consultation and answer it.'), array(
                                $link(lang('My Consultations'), '{{page:my_consultations}}', 'btn btn-outline-primary rounded-0 px-4'),
                                $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-outline-primary rounded-0 px-4'),
                            )),
                        $account_card('bi-person-workspace', lang('Applications Card'), lang('Your job applications'),
                            lang('See where each job application stands and read our messages about it.'), array(
                                $link(lang('My Applications'), '{{page:my_applications}}', 'btn btn-outline-primary rounded-0 px-4'),
                            )),
                        $el('div', 'p-4 bg-body-tertiary', lang('Inbox Card'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('A question that is not about a project?'), '', lang('Text')),
                            )),
                            $para(lang('Invoices, press or anything else: write from the contact page, and the answer comes to your inbox.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-secondary rounded-0'),
                        )),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

    'staff_enquiries_new'     => $staff_enquiries_new,
    'staff_consultations_new' => $staff_consultations_new,

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'            => 'registration',
            'show_remember_me'      => true,
            'login_page_id'         => '{{tab:login}}',
            'redirect_page_id'      => '{{tab:my_account}}',
            'welcome_email_page_id' => '{{tab:email_account_welcome}}',
            'welcome_email_subject' => lang('Welcome! Your account is ready'),
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array(
            'regionType'          => 'forgot_password',
            'login_page_id'       => '{{tab:login}}',
            'reset_email_page_id' => '{{tab:email_password_reset}}',
            'reset_email_subject' => lang('Reset your password'),
        ),
        'tree'   => 'starter',
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    // The client's invoices and returns from the ERP, under the account
    // overview on the same page; it draws nothing for a member without an
    // ERP account, so a site that does not use the ERP never sees it.
    'account_invoices' => array(
        'page'   => 'my_account',
        'slug'   => lang('invoices'),
        'config' => array('regionType' => 'account_invoices', 'max_results' => 50),
        'tree'   => 'starter',
    ),
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),

    // The account e-mails' widgets, drawn for the member the e-mail is
    // sent to. The reset e-mail's button carries the set password link: a
    // page without it is not sent (the built-in e-mail goes instead).
    'account_email_welcome' => array(
        'page'   => 'email_account_welcome',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome!')),
            $mail_account_greeting(),
            $mail_para(lang('Your account is ready. Your project enquiries, your consultations, your job applications and our messages are all in one place.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_para(lang('If you have any questions, we are here to help.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#52525b;margin:24px 0 0'),
        )),
    ),
    'account_email_reset' => array(
        'page'   => 'email_password_reset',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Reset your password')),
            $mail_account_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_para(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#52525b;margin:24px 0 0'),
        )),
    ),

    // The staff's Journal form: an article with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('ai-post-title', lang('Title'), pg_cf_with_role($input('ai-post-title', 'text', 'title', 'off'), 'title')),
            $field('ai-post-summary', lang('Summary'), pg_cf_with_role($textarea('ai-post-summary', 'summary', 3), 'description')),
            $field('ai-post-cover', lang('Cover Image'), pg_cf_with_role($file('ai-post-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('ai-post-content', lang('Content'), $textarea('ai-post-content', 'content', 14)),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff page's directory form: one person.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('ai-staff-name', lang('Full Name'), $input('ai-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('ai-staff-title', lang('Job Title'), $input('ai-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('ai-staff-department', lang('Department'), $input('ai-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('ai-staff-photo', lang('Photo'), $file('ai-staff-photo', 'photo', 'public', 'image/*'))), '6'),
                $col(array($field('ai-staff-email', lang('Email'), $input('ai-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('ai-staff-phone', lang('Phone'), $input('ai-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('ai-staff-bio', lang('A few words'), $textarea('ai-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory signed-in clients see: square portraits, as on the
    // team page.
    'staff_directory' => array(
        'page'   => 'staff_directory',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:staff_entry}}',
            'items_per_page'      => 24,
            'order_by_field'      => 'full_name',
            'order_by_direction'  => 'ASC',
            'search_enabled'      => true,
            'search_fields'       => array('full_name', 'job_title', 'department'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by name or department...'),
            'empty_message'       => lang('The directory is empty for now.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 border', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'object-fit-cover', 'flex-shrink-0',
                            $portrait($team[0]['name'], 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', $team[0]['name'], 'pai-display h6 mb-0', $bind('full_name')),
                            $para($team[0]['role'], 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span($departments['architecture'], 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span('leyla@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span($team[0]['bio'], 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
