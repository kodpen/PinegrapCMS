<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", the account and staff part: signing in, up
 * and out, My Account and its pages, the staff directory, the staff area
 * (the newest lesson requests and stay enquiries, the shortcuts to the
 * collections' forms, the News form with its first posts, the directory's
 * form), the account e-mails, and the widgets that draw them (the account
 * bar's login region and My Account included). Included after parts.php,
 * cards.php, mail.php, lessons.php and enquiries.php in the same scope (the
 * staff area's lists are their request lists with their rows); returns
 * array('pages' => ..., 'widgets' => ...).
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
// otherwise.
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

// The guest's own pages, behind the registration folder: the overview with
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

// The resort's team as signed-in guests see them: the staff's own list,
// kept from the staff area.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The ski school, guest services, the lifts and the ski patrol, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the morning's checklist and the newest lesson
// requests and stay enquiries side by side, then the staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('a', 'd-flex flex-column gap-3 h-100 p-4 rounded-4 border text-decoration-none link-body-emphasis', $title, array(
            $icon_tile($icon_name, 'fs-5 align-self-start'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-semibold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '3');
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
        $el('section', 'psk-hero py-5 bg-primary-subtle border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Staff only'), 'psk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Good morning on the mountain'), 'psk-display display-6 mb-3'),
                        $para(lang('Lesson requests, stay enquiries and the guests\' messages are kept here. Open one, answer under it and press Change Status; what you write reaches the guest by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('Update the slopes'), '{{page:slopes}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Morning Checklist'), array(
                            $heading('h2', lang('Before the lifts open'), 'h6 fw-semibold mb-3'),
                            $check_list(array(
                                lang('Set each slope and lift to Open, Closed or On Hold.'),
                                lang('Write today\'s snow depth, wind and avalanche danger on the conditions page.'),
                                lang('Answer the lesson requests that came in overnight.'),
                                lang('Send an offer for each new stay enquiry.'),
                            ), 'list-unstyled small d-grid gap-2 mb-0'),
                        )),
                    ), '', '5'),
                ), '4', '', 'align-items-center'),
            )),
        )),
        $el('section', 'py-5', lang('Newest Requests'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Ski school'), lang('Lesson Requests'), lang('All Lesson Requests'), '{{page:staff_lessons}}'),
                        $widget('staff_lessons_new'),
                    ), '', '6'),
                    $col(array(
                        $section_header(lang('Lodging'), lang('Stay Enquiries'), lang('All Stay Enquiries'), '{{page:staff_enquiries}}'),
                        $widget('staff_enquiries_new'),
                    ), '', '6'),
                ), '5'),
            )),
        )),
        $el('section', 'pb-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-signpost-split', lang('New Slope'), lang('Add a slope with its difficulty, area and lift; its status is set each morning.'), '{{page:slope_new}}'),
                    $staff_shortcut('bi-arrow-up-right', lang('New Lift'), lang('Add a gondola, a chairlift, a drag lift or a magic carpet to the lifts list.'), '{{page:lift_new}}'),
                    $staff_shortcut('bi-calendar-plus', lang('New Event'), lang('Publish a race, a torchlight descent or a night on the slopes on the events page.'), '{{page:event_new}}'),
                    $staff_shortcut('bi-snow2', lang('Conditions'), lang('See the conditions page as guests see it; its words are edited in the designer.'), '{{page:conditions}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-journal-plus', lang('New Article'), lang('Write a post; it appears under News as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory signed-in guests see.'), '{{page:staff_entry}}'),
                    $staff_shortcut('bi-person-lines-fill', lang('Staff Directory'), lang('The team as signed-in guests see it.'), '{{page:staff_directory}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The blog's first posts, written into its form when the form is made: the
// template carries them, so they are there on every site. Each is edited or
// deleted like any other post.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('How we groom the slopes overnight'),
        'summary'     => lang('While the village sleeps, the snow groomers work until dawn so the first run of the day is smooth.'),
        'content'     => $post_body(array(
            lang('The groomers set out after the last skiers come down, when the snow is cold enough to hold its shape.'),
            lang('Each driver has a share of the slopes and works them from the top, pushing back the snow skiers scraped to the sides.'),
            lang('By first light the ski patrol checks every slope, and only then are the lifts opened.'),
        )),
        'cover_image' => $photo('post-1', 1200, 675),
    ),
    array(
        'title'       => lang('Your child\'s first week at the ski school'),
        'summary'     => lang('From the magic carpet to the first green slope: what the Snow Cubs learn, day by day.'),
        'content'     => $post_body(array(
            lang('On the first morning the children meet their instructor at the meeting point and learn to walk on skis on the flat.'),
            lang('By the third day most of them ride the magic carpet on their own and stop with a snowplough.'),
            lang('On the last day the group skis a green slope together, and every child goes home with a medal.'),
        )),
        'cover_image' => $photo('post-2', 1200, 675),
    ),
    array(
        'title'       => lang('Reading the avalanche danger scale'),
        'summary'     => lang('What the five levels mean, and why the marked slopes stay safe when the off-piste does not.'),
        'content'     => $post_body(array(
            lang('The scale goes from one, low, to five, very high, and the ski patrol sets it each morning after checking the snow.'),
            lang('The marked slopes are made safe before they open; the danger level speaks to anyone leaving them.'),
            lang('Off the marked slopes, go only with a guide, a transceiver, a shovel and a probe.'),
        )),
        'cover_image' => $photo('post-3', 1200, 675),
    ),
    array(
        'title'       => lang('Planning a group trip: what clubs and schools ask us'),
        'summary'     => lang('Rooms, lift passes, lessons and equipment for the whole group: answers before you send an enquiry.'),
        'content'     => $post_body(array(
            lang('Tell us the size of the group, the number of nights and the levels of your skiers in a group enquiry.'),
            lang('Guest services writes back with an offer for the stay, the lift passes and, if you want them, the lessons.'),
            lang('Equipment for the whole group can be set out at the rental shop before you arrive.'),
        )),
        'cover_image' => $photo('post-4', 1200, 675),
    ),
);

// The staff's forms: the blog and the staff directory.
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
$account_pages[] = $staff_form_page('blog_new', lang('new-article'), lang('New Article'),
    lang('Write the post; it appears under News as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('News'),
        'collection'           => 1,
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample posts are written in when the form is made,
        // so the blog and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
        // The cover is the posts' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$account_pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in guests see the directory from their account.'), 'staff_form',
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

// The guest's account menu: the lesson requests and stay enquiries first.
$account_links = array(
    $account_link('bi-person-arms-up', lang('My Lessons'), '{{page:my_lessons}}'),
    $account_link('bi-house-door', lang('My Enquiries'), '{{page:my_enquiries}}'),
    $account_link('bi-snow2', lang('Conditions'), '{{page:conditions}}'),
    $account_link('bi-calendar-event', lang('Events'), '{{page:events}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// A card of My Account that leads to one kind of request: its icon, its
// words, the guest's own list and the form.
$account_request_card = function ($icon_name, $name, $title, $text, $list_text, $list_key, $new_text, $new_key) use ($el, $heading, $para, $link, $icon_tile) {
    return $el('div', 'p-4 rounded-4 border mb-4', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $icon_tile($icon_name, 'fs-4'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h5 fw-semibold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link($list_text, '{{page:' . $list_key . '}}', 'btn btn-primary px-4'),
                $link($new_text, '{{page:' . $new_key . '}}', 'btn btn-outline-primary px-4'),
            )),
        )),
    ));
};

// The staff area's newest requests: the request lists of lessons.php and
// enquiries.php with their staff rows on this page, the last eight without
// the search.
$staff_newest = function ($form_key, $view_key, $row, $search_fields, $empty) use ($request_list) {
    $list = $request_list('staff', $form_key, $view_key, $row, $search_fields, '', $empty);
    $list['config']['items_per_page'] = 8;
    unset($list['config']['search_enabled'], $list['config']['search_fields'],
        $list['config']['search_width'], $list['config']['search_label']);
    return $list;
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
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary rounded-pill text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
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
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Lessons'), '{{page:my_lessons}}'),
                    $login_page_item(lang('My Enquiries'), '{{page:my_enquiries}}'),
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

    // My Account: the member's card and menu beside the lesson and stay
    // cards, the orders (passes and rental, drawn only for a member who has
    // any), today on the mountain and the inbox card.
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
                        $el('div', 'psk-hero p-4 rounded-4 text-bg-primary mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'psk-eyebrow small text-uppercase fw-semibold mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'psk-display h4 mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_request_card('bi-person-arms-up', lang('Lessons Card'), lang('Your lesson requests'),
                            lang('See whether an instructor is booked for you, and read the ski school\'s messages.'),
                            lang('My Lessons'), 'my_lessons', lang('Request a Lesson'), 'lesson_request'),
                        $account_request_card('bi-house-door', lang('Stay Card'), lang('Your stay enquiries'),
                            lang('Read the offers of guest services for your stay or your group, and answer them.'),
                            lang('My Enquiries'), 'my_enquiries', lang('Send an Enquiry'), 'group_enquiry'),
                        $heading('h2', lang('My Orders'), 'h5 fw-semibold mb-3', $show_when('has_orders')),
                        $el('div', 'mb-4', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                        $row(array(
                            $col(array(
                                $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Mountain Card'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-snow2', 'text-primary'),
                                        $span(lang('Today on the mountain'), '', lang('Text')),
                                    )),
                                    $para(lang('Snow depth, open slopes and lifts, as the staff set them this morning.'), 'small text-body-secondary mb-3'),
                                    $link(lang('See the conditions'), '{{page:conditions}}', 'btn btn-sm btn-outline-primary'),
                                )),
                            ), '6'),
                            $col(array(
                                $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Inbox Card'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-inbox', 'text-primary'),
                                        $span(lang('Another question?'), '', lang('Text')),
                                    )),
                                    $para(lang('Passes, rental or anything else: write from the contact page, and the answer comes to your inbox.'), 'small text-body-secondary mb-3'),
                                    $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary'),
                                )),
                            ), '6'),
                        ), '3'),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

    'staff_lessons_new'   => $staff_newest('lesson_request', 'lesson_view', $lesson_row, array('first_name'), lang('No lesson requests yet.')),
    'staff_enquiries_new' => $staff_newest('group_enquiry', 'enquiry_view', $enquiry_row, array('first_name'), lang('No stay enquiries yet.')),

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
            $mail_account_greeting(),
            $mail_para(lang('Your account is ready. Your lesson requests, your stay enquiries and our messages are all in one place.')),
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
            $mail_account_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_para(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#4b5563;margin:24px 0 0'),
        )),
    ),

    // The staff's News form: a post with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('skr-post-title', lang('Title'), pg_cf_with_role($input('skr-post-title', 'text', 'title', 'off'), 'title')),
            $field('skr-post-summary', lang('Summary'), pg_cf_with_role($textarea('skr-post-summary', 'summary', 3), 'description')),
            $field('skr-post-cover', lang('Cover Image'), pg_cf_with_role($file('skr-post-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('skr-post-content', lang('Content'), $textarea('skr-post-content', 'content', 14)),
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
                $col(array($field('skr-staff-name', lang('Full Name'), $input('skr-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('skr-staff-title', lang('Job Title'), $input('skr-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('skr-staff-department', lang('Department'), $input('skr-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('skr-staff-photo', lang('Photo'), $file('skr-staff-photo', 'photo', 'public', 'image/*'))), '6'),
                $col(array($field('skr-staff-email', lang('Email'), $input('skr-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('skr-staff-phone', lang('Phone'), $input('skr-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('skr-staff-bio', lang('A few words'), $textarea('skr-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory signed-in guests see: a portrait over each name.
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
                    $el('div', 'h-100 p-4 rounded-4 border text-center', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'mx-auto mb-3',
                            $portrait(lang('Can Öztürk'), 128), lang('Photo')),
                        $heading('h2', lang('Can Öztürk'), 'h6 fw-semibold mb-0', $bind('full_name')),
                        $para(lang('Mountain Operations Manager'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                            $icon('bi-diagram-3', 'text-primary me-1'),
                            $span(lang('Lifts and Slopes'), 'pg-field-value', lang('Value'), $bind('department')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                            $icon('bi-envelope', 'text-primary me-1'),
                            $span('can@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary me-1'),
                            $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                        )),
                        $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                            $span(lang('Ask me which slopes open first after a fresh snowfall.'), 'pg-field-value', lang('Value'), $bind('bio')),
                        )),
                    )),
                ), '6', '3'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
