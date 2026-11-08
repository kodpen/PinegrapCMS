<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", the account and staff part: signing in,
 * up and out, My Account and its pages, the staff directory, the staff area
 * (the way to the academy registrations, the trial requests and the
 * sponsorship enquiries, the match, player and event forms, Club News'
 * article form with its first articles, the directory's form), the account
 * e-mails, and the widgets that draw them (the account bar's login region
 * and My Account included). Included after parts.php and mail.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * The helpers of this file are named ac_…, so they never take the place
 * of a helper another page file of the folder defines in the same scope.
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
// shop is on (the address book is a shop page, and only the shop sells the
// memberships that open the members' area).
$ac_panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$ac_shop      = defined('ECOMMERCE') && ECOMMERCE;

$ac_pages = array();

// ── Signing in and out ──────────────────────────────────────────────────

// A page that only places its widget, in the public folder unless it says
// otherwise.
$ac_sign_page = function ($key, $name, $title, $widget_key, $folder = 'public') use ($page, $widget) {
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
$ac_pages[] = $ac_sign_page('login', lang('login'), lang('Log In'), 'login');
$ac_pages[] = $ac_sign_page('register', lang('register'), lang('Sign Up'), 'register');
$ac_pages[] = $ac_sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$ac_pages[] = $ac_sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$ac_pages[] = $ac_sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// ── My account ──────────────────────────────────────────────────────────

// The member's own pages, behind the registration folder: the overview
// with the invoices and the security settings under it, then the profile,
// the password, the e-mail preferences and, with the shop, the address book.
$ac_pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security')), false),
);
$ac_pages[] = $ac_sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile', 'registration');
$ac_pages[] = $ac_sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password', 'registration');
$ac_pages[] = $ac_sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences', 'registration');
$ac_pages[] = array_merge($ac_sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book', 'registration'),
    array('requires' => 'ecommerce'));

// The club's coaches and volunteers as signed-in members see them: the
// staff's own list, kept from the staff area.
$ac_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The coaches, the committee and the volunteers who run the club, what each of them looks after and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the welcome with the way to the requests,
// then the staff's shortcuts.
$ac_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary text-decoration-none link-body-emphasis psp-lift', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-semibold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '4');
};
$ac_pages[] = array(
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
                        $span(lang('Staff only'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'psp-display display-6 mb-3'),
                        $para(lang('The academy registrations, the trial requests, the sponsorship enquiries, the matches, the players and Club News are kept here. Open a request and press Change Status when you answer it; what you write under it reaches the family or the player by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Academy Registrations'), '{{page:staff_registrations}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Trial Requests'), '{{page:staff_trials}}', 'btn btn-outline-primary rounded-pill px-4'),
                            $link(lang('Open the control panel'), $ac_panel_url, 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $ac_shortcut('bi-mortarboard', lang('Academy Registrations'), lang('Every registration for a place in the academy, newest first. Open one to book a taster session, offer a place or change its status.'), '{{page:staff_registrations}}'),
                    $ac_shortcut('bi-person-plus', lang('Trial Requests'), lang('Every request to train with a team, newest first. Write the training night under it and keep its status up to date.'), '{{page:staff_trials}}'),
                    $ac_shortcut('bi-award', lang('Sponsorship Enquiries'), lang('Every enquiry about a sponsorship package. Answer below each one.'), '{{page:staff_sponsors}}'),
                    $ac_shortcut('bi-calendar-plus', lang('New Match'), lang('Add a fixture; after the final whistle, edit it to write the score and the report.'), '{{page:match_new}}'),
                    $ac_shortcut('bi-person-badge', lang('New Player'), lang('Add a player with a photo, a position and a shirt number; they appear in the squad at once.'), '{{page:player_new}}'),
                    $ac_shortcut('bi-calendar-event', lang('New Club Event'), lang('Add a quiz night, an awards evening or an open day to Club Events.'), '{{page:event_new}}'),
                    $ac_shortcut('bi-journal-text', lang('New Article'), lang('Write for Club News; the article appears there as soon as you send it.'), '{{page:blog_new}}'),
                    $ac_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $ac_shortcut('bi-person-lines-fill', lang('Add to the Staff Directory'), lang('Add a coach or a volunteer to the directory members see.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// Club News' first articles, written into its form when the form is made:
// the template carries them, so they are there on every site. Each is
// edited or deleted like any other post. None names a date: the article's
// date is the record's own.
$ac_post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$ac_sample_posts = array(
    array(
        'title'       => lang('Five things to bring to your first training session'),
        'summary'     => lang('Boots, water and a good attitude: what a new player needs on the first night, and what the club lends.'),
        'content'     => $ac_post_body(array(
            lang('Bring boots for grass and trainers for the all-weather pitch, shin pads, a water bottle and something warm for after the session.'),
            lang('The club lends bibs and balls, and the changing rooms in the clubhouse are open an hour before training starts.'),
            lang('Come ten minutes early and say hello to the coach: tell them where you like to play and how long it has been since your last game.'),
        )),
        'cover_image' => $photo('club-news-1', 1200, 675),
    ),
    array(
        'title'       => lang('Late winner keeps the first team top of the league'),
        'summary'     => lang('A goal in the last minute at Mill Lane, a full stand and a clubhouse that stayed open long after the final whistle.'),
        'content'     => $ac_post_body(array(
            lang('It looked like a draw until the last minute, when a corner fell to our captain at the far post and the stand behind the goal stood up as one.'),
            lang('The coach praised the squad\'s patience: the visitors defended deep all afternoon, and the team kept the ball and waited for the chance.'),
            lang('Thank you to everyone who came, and to the volunteers in the kitchen and on the gate. The next home match is on the fixtures page.'),
        )),
        'cover_image' => $photo('club-news-2', 1200, 675),
    ),
    array(
        'title'       => lang('The academy opens its doors for new players'),
        'summary'     => lang('Taster sessions for every age group: how to register a child, and what happens after the first session.'),
        'content'     => $ac_post_body(array(
            lang('Every age group of the academy has room for new players this season, from the youngest to the under-sixteens.'),
            lang('Register your child on the academy page. The coaches invite you to a taster session, and after it we offer a place or a spot on the waiting list.'),
            lang('All our academy coaches are qualified and checked, and a parent is always welcome to stay and watch.'),
        )),
        'cover_image' => $photo('club-news-3', 1200, 675),
    ),
    array(
        'title'       => lang('Volunteers wanted for match days'),
        'summary'     => lang('The gate, the kitchen, the kit and the line: a few hours on a match day keep the club running.'),
        'content'     => $ac_post_body(array(
            lang('The club is run by volunteers, and every match day needs a few more hands: at the gate, in the kitchen, with the kit or running the line.'),
            lang('No experience is needed. Choose the job that suits you, and come once a month or every week, as you like.'),
            lang('Write to us from the contact page and tell us what you would like to do; we will find you a place on the next home match.'),
        )),
        'cover_image' => $photo('club-news-4', 1200, 675),
    ),
);

// The staff's forms: Club News and the staff directory.
$ac_form_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
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
                            $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$ac_pages[] = $ac_form_page('blog_new', lang('new-article'), lang('New Article'),
    lang('Write the article; it appears in Club News as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Club News'),
        'collection'           => 1,
        'confirmation_message' => lang('The article is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample articles are written in when the form is
        // made, so Club News and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $ac_sample_posts,
        // The cover is the articles' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$ac_pages[] = $ac_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in members see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'collection'           => 1,
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// ── Account e-mails ─────────────────────────────────────────────────────

// The welcome after a sign-up and the password reset link. Their Account
// E-mail widgets fill in the member's name and links.
$ac_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_account_welcome',
    'name'  => lang('email-account-welcome'),
    'title' => lang('Account Welcome E-mail'),
    'tree'  => $mail_page(array($widget('account_email_welcome'))),
));
$ac_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_password_reset',
    'name'  => lang('email-password-reset'),
    'title' => lang('Password Reset E-mail'),
    'tree'  => $mail_page(array($widget('account_email_reset'))),
));
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$ac_mail_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};

// ── Widget parts ────────────────────────────────────────────────────────

$ac_login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$ac_login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$ac_login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// A link of the account menu; $flag shows it only while that widget flag
// is on.
$ac_account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};

// The member's account menu: the registrations and trials first, then the
// members' area while the shop sells the memberships that open it.
$ac_account_links = array(
    $ac_account_link('bi-clipboard-check', lang('My Registrations'), '{{page:my_registrations}}'),
);
if ($ac_shop) $ac_account_links[] = $ac_account_link('bi-shield-check', lang('Members\' Area'), '{{page:members}}');
$ac_account_links[] = $ac_account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}');
$ac_account_links[] = $ac_account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url');
if ($ac_shop) $ac_account_links[] = $ac_account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$ac_account_links[] = $ac_account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$ac_account_links[] = $ac_account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$ac_account_links[] = $ac_account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$ac_account_links[] = $ac_account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// A card of My Account: an icon, a title and a sentence, and its buttons.
$ac_card = function ($name, $icon_name, $title, $text, $buttons, $class) use ($el, $heading, $para, $icon) {
    return $el('div', $class, $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $icon($icon_name, 'fs-2 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h5 fw-semibold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        )),
    ));
};

// ── Widgets ─────────────────────────────────────────────────────────────

$ac_widgets = array(

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
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary rounded-pill text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 p-0 border-0 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image($portrait(lang('Jane Cooper'), 64), lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $ac_login_divider(),
                    $ac_login_item(lang('My Account'), '__my_account_url'),
                    $ac_login_page_item(lang('My Registrations'), '{{page:my_registrations}}'),
                    $ac_login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $ac_login_item(lang('Edit my profile'), '__profile_url'),
                    $ac_login_item(lang('Staff area'), '__staff_url', 'is_staff'),
                    $ac_login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $ac_login_divider(),
                    $ac_login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // My Account: the member's card and menu beside the registrations and
    // fixtures cards and the orders (drawn only for a member who has any).
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
                        $el('div', 'p-4 rounded-4 bg-primary-subtle mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-semibold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $ac_account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $ac_card(lang('Registrations Card'), 'bi-clipboard-check', lang('Your registrations and trials'),
                            lang('Follow each academy registration and trial request, and read the coaches\' answers.'), array(
                                $link(lang('My Registrations'), '{{page:my_registrations}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Academy Registration'), '{{page:academy_register}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $ac_card(lang('Match Days Card'), 'bi-calendar-event', lang('Match days'),
                            lang('The coming matches of every team, the latest results and the club\'s events.'), array(
                                $link(lang('Fixtures'), '{{page:fixtures}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Club Events'), '{{page:events}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $heading('h2', lang('My Orders'), 'h5 fw-semibold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

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
    // The member's invoices and returns from the ERP, under the account
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
            $ac_mail_greeting(),
            $mail_para(lang('Your account is ready. Your academy registrations, your trial requests and your orders, and our messages about them, are all in one place.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_para(lang('If you have any questions, we are here to help.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#4b5563;margin:24px 0 0'),
        )),
    ),
    'account_email_reset' => array(
        'page'   => 'email_password_reset',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Reset your password')),
            $ac_mail_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_para(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#4b5563;margin:24px 0 0'),
        )),
    ),

    // The staff's article form: a Club News article with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('sp-post-title', lang('Title'), pg_cf_with_role($input('sp-post-title', 'text', 'title', 'off'), 'title')),
            $field('sp-post-summary', lang('Summary'), pg_cf_with_role($textarea('sp-post-summary', 'summary', 3), 'description')),
            $field('sp-post-cover', lang('Cover Image'), pg_cf_with_role($file('sp-post-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('sp-post-content', lang('Content'), $textarea('sp-post-content', 'content', 14)),
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
                $col(array($field('sp-staff-name', lang('Full Name'), $input('sp-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('sp-staff-title', lang('Job Title'), $input('sp-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('sp-staff-department', lang('Department'), $input('sp-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('sp-staff-photo', lang('Photo'), $file('sp-staff-photo', 'photo', 'public', 'image/*'))), '6'),
                $col(array($field('sp-staff-email', lang('Email'), $input('sp-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('sp-staff-phone', lang('Phone'), $input('sp-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('sp-staff-bio', lang('A few words'), $textarea('sp-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory signed-in members see.
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
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                            $portrait(lang('Emre Şahin'), 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Emre Şahin'), 'h6 fw-semibold mb-0', $bind('full_name')),
                            $para(lang('First Team Coach'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Coaching'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span('emre@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Ask me about the first team\'s training nights, a trial or the match-day volunteers.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $ac_pages, 'widgets' => $ac_widgets);
