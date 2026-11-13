<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", the account and staff part: signing
 * in, up and out, My Account and the member's account pages, the staff
 * directory, the staff area (the way to the member posts, the reports and
 * the profiles, the Community News post form with its first posts, the
 * directory's form), the account e-mails, and the widgets that draw them
 * (the account bar's login region and My Account included). Included after
 * parts.php and mail.php in the same scope (it uses mail.php's $mail_settings,
 * $mail_page, $mail_heading, $mail_para, $mail_button and $mail_style);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The control panel's address for the staff area's button.
$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';

$account_pages = array();

// ── Signing in and out ──────────────────────────────────────────────────

// A page that only places its widget: in the public folder on the plain
// page shell, in the registration folder under the member bar. The account
// pages leave the join band off.
$sign_page = function ($key, $name, $title, $widget_key, $folder = 'public') use ($page, $member_page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => $folder,
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $folder === 'registration' ? $member_page(array($widget($widget_key))) : $page(array($widget($widget_key)), false),
    );
};
$account_pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$account_pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$account_pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$account_pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$account_pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// ── My account ──────────────────────────────────────────────────────────

// The member's own pages, behind the registration folder: the overview with
// the invoices and the security settings under it, then the account details
// (the user account; the public profile is my_profile), the password and the
// e-mail preferences.
$account_pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $member_page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security'))),
);
$account_pages[] = $sign_page('profile', lang('account-details'), lang('Account Details'), 'profile', 'registration');
$account_pages[] = $sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password', 'registration');
$account_pages[] = $sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences', 'registration');

// The community's moderators and team as signed-in members see them: the
// staff's own list, kept from the staff area.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $member_page(array(
        $page_header(lang('Staff Directory'), lang('The moderators and the team who look after the community, read the reports and answer your messages.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    )),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the welcome with the way to the moderation
// lists, then the staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary text-decoration-none link-body-emphasis', $title, array(
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
                        $span(lang('Staff only'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'display-6 fw-semibold mb-3'),
                        $para(lang('Members\' posts, their reports, the profiles, the conversations and Community News are looked after here. Open a report, read the post it names and hide the post by its status when it breaks the guidelines; what you write under a report reaches the member by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('All Reports'), '{{page:staff_reports}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('All Member Posts'), '{{page:staff_posts}}', 'btn btn-outline-primary rounded-pill px-4'),
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-flag', lang('All Reports'), lang('Every report a member sent about a post, newest first. Open one, look at the post and answer the member.'), '{{page:staff_reports}}'),
                    $staff_shortcut('bi-images', lang('All Member Posts'), lang('Every photo members shared, the hidden ones too. Change a post\'s status to hide it from the community or show it again.'), '{{page:staff_posts}}'),
                    $staff_shortcut('bi-people', lang('All Profiles'), lang('Every member\'s public profile: their name, their picture and the words about them.'), '{{page:staff_members}}'),
                    $staff_shortcut('bi-megaphone', lang('New Community News Post'), lang('Announce a photo challenge, a new feature or a change to the guidelines; it appears in Community News as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-person-lines-fill', lang('Add to the Staff Directory'), lang('Add a moderator or a colleague to the directory members see from their account.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// Community News's first posts, written into its form when the form is
// made: the template carries them, so they are there on every site. Each is
// edited or deleted like any other post. None names a date: the post's date
// is the record's own. The members named are the community's own samples
// ($members), named as their cards name them.
$news_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$news_samples = array(
    array(
        'title'       => lang('This month\'s photo challenge: windows'),
        'summary'     => lang('Look through a window, or at one: share your picture with the tag windows and see what the others found.'),
        'content'     => $news_body(array(
            lang('Every month we pick one word and the whole community goes out to photograph it. This month the word is windows.'),
            lang('A kitchen window at breakfast, a shop window at night, a train window on a long trip: any window counts. Add the tag windows to your post so we can find it.'),
            lang('At the end of the month the moderators pick a few favourites for the home page. Lena Hoffmann\'s window still lifes started the idea, so she will help us choose.'),
        )),
        'cover_image' => $photo('news-windows', 1200, 675),
    ),
    array(
        'title'       => lang('Meet the members: bread, film and a greyhound'),
        'summary'     => lang('Three members tell us what they shoot, why they started and the post they are proudest of.'),
        'content'     => $news_body(array(
            lang('Tom Fletcher bakes most Saturdays and photographs every loaf, the failures too. His tip: shoot the crumb by the window, never under the kitchen lamp.'),
            lang('Ayla Demir walks to the harbour before sunrise with an old film camera. She never knows what she has until the roll comes back.'),
            lang('Sofia Reyes shares Pip, her rescue greyhound, in every patch of sun he finds. Her posts have the kindest comments in the community.'),
        )),
        'cover_image' => $photo('news-members', 1200, 675),
    ),
    array(
        'title'       => lang('A line of alt text for every photo'),
        'summary'     => lang('Why the new post form asks you to describe your picture, and how to write a good description in one sentence.'),
        'content'     => $news_body(array(
            lang('Some members follow the community with a screen reader. The alt text is what lets them enjoy your photo too.'),
            lang('Say what is in the picture and what matters about it, in one plain sentence: who, what and where is usually enough.'),
            lang('You can add or change the description of an older post at any time from My Posts.'),
        )),
        'cover_image' => $photo('news-alt', 1200, 675),
    ),
    array(
        'title'       => lang('How we read your reports'),
        'summary'     => lang('What happens after you report a post: who reads it, what we look at and when we hide a photo.'),
        'content'     => $news_body(array(
            lang('Every report reaches a moderator, and a person reads each one. We look at the post, its comments and the guidelines it may break.'),
            lang('When a post breaks the guidelines we hide it and tell the member why. When it does not, we tell you, and the post stays.'),
            lang('You follow each report you sent under My Reports, with our answer below it.'),
        )),
        'cover_image' => $photo('news-reports', 1200, 675),
    ),
);

// The staff's forms: Community News and the staff directory.
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
                            $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$account_pages[] = $staff_form_page('blog_new', lang('new-community-news-post'), lang('New Community News Post'),
    lang('Write the post; it appears in Community News as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Community News'),
        'collection'           => 1,
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample posts are written in when the form is made,
        // so Community News and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $news_samples,
        // The cover is the posts' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$account_pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
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
        $span(lang('Ayla'), '', lang('First Name'), $bind('__user_first_name')),
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

// The member's account menu: their posts, the saved ones, the public
// profile and the reports first.
$account_links = array(
    $account_link('bi-grid-3x3', lang('My Posts'), '{{page:my_posts}}'),
    $account_link('bi-bookmark', lang('Saved Posts'), '{{page:saved}}'),
    $account_link('bi-person-badge', lang('My Profile'), '{{page:my_profile}}'),
    $account_link('bi-flag', lang('My Reports'), '{{page:my_reports}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-gear', lang('Account Details'), '#', '__profile_url'),
    $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url'),
    $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url'),
    $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}'),
    // Drawn only for the members the staff area's private folder lets in.
    $account_link('bi-person-badge-fill', lang('Staff Area'), '#', '__staff_url', 'is_staff'),
);

// A card of My Account: an icon, a title and a sentence, and its buttons.
$account_card = function ($name, $icon_name, $title, $text, $buttons, $class) use ($el, $heading, $para, $icon) {
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
                $link(lang('Join Now'), '#', 'btn btn-sm btn-outline-primary rounded-pill text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 p-0 border-0 link-body-emphasis text-decoration-none text-nowrap dropdown-toggle', lang('Account Button'), array(
                    $image($photo('avatar-ayla', 64, 64), lang('Ayla Demir'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Ayla'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Ayla Demir'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span('ayla.demir@example.com', 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Posts'), '{{page:my_posts}}'),
                    $login_page_item(lang('Saved Posts'), '{{page:saved}}'),
                    $login_page_item(lang('My Profile'), '{{page:my_profile}}'),
                    $login_page_item(lang('My Reports'), '{{page:my_reports}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Account Details'), '__profile_url'),
                    $login_item(lang('Staff area'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // My Account: the member's card and menu beside the cards of their
    // posts, their profile and their saved posts and reports. No orders:
    // the community has no shop.
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
                            $heading('h1', lang('Ayla Demir'), 'h4 fw-semibold mb-1', $bind('__display_name')),
                            $para('ayla.demir@example.com', 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card(lang('Posts Card'), 'bi-camera', lang('Your photos'),
                            lang('Share a new photo, or change the caption, the tags and the alt text of the ones you posted.'), array(
                                $link(lang('My Posts'), '{{page:my_posts}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Share a Photo'), '{{page:post_new}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $account_card(lang('Profile Card'), 'bi-person-badge', lang('Your public profile'),
                            lang('Your picture, your name and a few words: what other members see when they open your posts.'), array(
                                $link(lang('My Profile'), '{{page:my_profile}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('People'), '{{page:people}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $account_card(lang('Saved and Reports Card'), 'bi-bookmark-heart', lang('Saved posts and reports'),
                            lang('The posts you saved to come back to, and the reports you sent with our answers.'), array(
                                $link(lang('Saved Posts'), '{{page:saved}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('My Reports'), '{{page:my_reports}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border'),
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
            'welcome_email_subject' => lang('Welcome to the community! Your account is ready'),
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
    // The member's invoices from the ERP, under the account overview on the
    // same page; it draws nothing for a member without an ERP account, so a
    // site that does not use the ERP never sees it.
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

    // The account e-mails' widgets, drawn for the member the e-mail is
    // sent to. The reset e-mail's button carries the set password link: a
    // page without it is not sent (the built-in e-mail goes instead).
    'account_email_welcome' => array(
        'page'   => 'email_account_welcome',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome to the community!')),
            $mail_account_greeting(),
            $mail_para(lang('Your account is ready. Set up your profile with a picture and a few words, then share your first photo: the community is waiting to see it.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_para(lang('Before you post, take a minute to read the community guidelines. If you have any questions, we are here to help.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#4b5563;margin:24px 0 0'),
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
            $mail_para(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#4b5563;margin:24px 0 0'),
        )),
    ),

    // The staff's Community News form: a post with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('sc-news-title', lang('Title'), pg_cf_with_role($input('sc-news-title', 'text', 'title', 'off'), 'title')),
            $field('sc-news-summary', lang('Summary'), pg_cf_with_role($textarea('sc-news-summary', 'summary', 3), 'description')),
            $field('sc-news-cover', lang('Cover Image'), pg_cf_with_role($file('sc-news-cover', 'cover_image', 'public'), 'media')),
            $field('sc-news-content', lang('Content'), $textarea('sc-news-content', 'content', 14)),
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
                $col(array($field('sc-staff-name', lang('Full Name'), $input('sc-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('sc-staff-title', lang('Job Title'), $input('sc-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('sc-staff-department', lang('Department'), $input('sc-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('sc-staff-photo', lang('Photo'), $file('sc-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('sc-staff-email', lang('Email'), $input('sc-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('sc-staff-phone', lang('Phone'), $input('sc-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('sc-staff-bio', lang('A few words'), $textarea('sc-staff-bio', 'bio', 3, false)),
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
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover psc-avatar', 'flex-shrink-0',
                            $photo('staff', 128, 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Deniz Kaya'), 'h6 fw-semibold mb-0', $bind('full_name')),
                            $para(lang('Community Moderator'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Moderation'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span('deniz@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Ask me about a report, a hidden post or the community guidelines.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
