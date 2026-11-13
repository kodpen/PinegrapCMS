<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", the account and staff part: signing in,
 * up and out, My Account and its pages, the staff directory, the staff
 * area (the way to the reservation requests and the private dining
 * enquiries, the Kitchen Journal's post form with its first posts, the
 * directory's form), the account e-mails, and the widgets that draw them
 * (the account bar's login region and My Account included). Included
 * after parts.php and mail.php in the same scope; returns
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

// The customer's own pages, behind the registration folder: the overview
// with the invoices and the security settings under it, then the profile,
// the password, the e-mail preferences and, with the shop, the address book.
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

// The restaurant's team as signed-in guests see it: the staff's own list, kept
// from the staff area.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The people who cook for you, look after your table and plan your private dinner, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the welcome with the way to the reservation
// requests and the private dining enquiries, then the staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-3 bg-body-tertiary text-decoration-none link-body-emphasis prt-lift', $title, array(
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
                        $para(lang('The reservation requests, the private dining enquiries, the menu, the events, the conversations and the Kitchen Journal are kept here. Open a request and press Change Status when you confirm the table or need something from the guest; what you write under it reaches the guest by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Reservation Requests'), '{{page:staff_reservations}}', 'btn btn-primary px-4'),
                            $link(lang('Private Dining Enquiries'), '{{page:staff_enquiries}}', 'btn btn-outline-primary px-4'),
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-outline-secondary px-4'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-calendar-check', lang('Reservation Requests'), lang('Every reservation request, newest first. Open one to confirm the table, ask the guest something or change its status.'), '{{page:staff_reservations}}'),
                    $staff_shortcut('bi-door-closed', lang('Private Dining Enquiries'), lang('Every private dining enquiry, newest first. Send the proposal and keep its status up to date.'), '{{page:staff_enquiries}}'),
                    $staff_shortcut('bi-egg-fried', lang('New Dish'), lang('Add a dish with its course, price, diet tags and photo; it appears on the menu at once.'), '{{page:dish_new}}'),
                    $staff_shortcut('bi-calendar-event', lang('New Event'), lang('Add a wine dinner, a cooking class or a supper; it appears in the events at once.'), '{{page:event_new}}'),
                    $staff_shortcut('bi-journal-text', lang('New Article'), lang('Write for the Kitchen Journal; the article appears there as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory customers see.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The Kitchen Journal's first articles, written into its form when the form
// is made: the template carries them, so they are there on every site. Each
// is edited or deleted like any other post. None names a date: the
// article's date is the record's own.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('What the market brought in this week'),
        'summary'     => lang('Early broad beans, wild garlic from the hills and the first strawberries: how a morning at the market becomes tonight\'s menu.'),
        'content'     => $post_body(array(
            lang('Every morning the chef walks to the market before the stalls are busy, and the menu is written after that walk, not before.'),
            lang('This week the growers brought the first broad beans, wild garlic picked in the hills and small strawberries that taste of the sun.'),
            lang('The beans go with the lamb, the garlic into a green oil for the bread, and the strawberries into a dessert with goat\'s milk ice cream.'),
        )),
        'cover_image' => $photo('journal-1', 1200, 675),
    ),
    array(
        'title'       => lang('Meet the farm that grows our vegetables'),
        'summary'     => lang('Half an hour from town, a family farm grows the leaves, roots and herbs on our plates, and keeps a row just for our kitchen.'),
        'content'     => $post_body(array(
            lang('We buy almost all our vegetables from one family farm in the valley, and we have done so since the restaurant opened.'),
            lang('Each winter we sit down with them and choose what to sow; some rows are grown only for our kitchen, from seeds they keep year to year.'),
            lang('Twice a season we close for a lunch at the farm with the whole team, so the cooks know where every leaf comes from.'),
        )),
        'cover_image' => $photo('journal-2', 1200, 675),
    ),
    array(
        'title'       => lang('Five wines from small growers worth knowing'),
        'summary'     => lang('Our sommelier picks five bottles from the list made by families who farm their own vines, and what to eat with each.'),
        'content'     => $post_body(array(
            lang('Most of our wine list comes from growers who tend their own vines and make a few thousand bottles a year.'),
            lang('Our sommelier chose five of them, from a crisp white of the coast to a red that has waited ten years in the cellar, each with a dish from the menu.'),
            lang('Ask for them by name at your table; each is also sold by the glass on the evenings of our wine dinners.'),
        )),
        'cover_image' => $photo('journal-3', 1200, 675),
    ),
    array(
        'title'       => lang('Bread from the wood oven, step by step'),
        'summary'     => lang('A sourdough started when the bakery opened, a long night of rising and an oven lit before dawn: the bread on your table.'),
        'content'     => $post_body(array(
            lang('The restaurant was a bakery before it was ours, and its wood oven still bakes our bread every day.'),
            lang('The dough rests through the night, the oven is lit before dawn, and the first loaves come out as the market opens.'),
            lang('On Saturday mornings the cooking class bakes with us; there is always a loaf to take home.'),
        )),
        'cover_image' => $photo('journal-4', 1200, 675),
    ),
);

// The staff's forms: the Kitchen Journal and the staff directory.
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
                            $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$account_pages[] = $staff_form_page('blog_new', lang('new-article'), lang('New Article'),
    lang('Write the article; it appears in the Kitchen Journal as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Kitchen Journal'),
        'collection'           => 1,
        'confirmation_message' => lang('The article is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample articles are written in when the form is
        // made, so the Kitchen Journal and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
        // The cover is the articles' share image (pg_cf_share_image_field_id()).
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

// The guest's account menu: the requests first.
$account_links = array(
    $account_link('bi-calendar-check', lang('My Reservations'), '{{page:my_reservations}}'),
    $account_link('bi-door-closed', lang('My Enquiries'), '{{page:my_enquiries}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

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
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
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
                    $login_page_item(lang('My Reservations'), '{{page:my_reservations}}'),
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

    // My Account: the member's card and menu beside the reservation and
    // private dining cards and the orders (drawn only for a member who has any).
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
                        $el('div', 'p-4 rounded-3 bg-primary-subtle mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-semibold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card(lang('Reservations Card'), 'bi-calendar-check', lang('Your reservations'),
                            lang('Follow each request until the table is confirmed, answer our questions and tell us about allergies.'), array(
                                $link(lang('My Reservations'), '{{page:my_reservations}}', 'btn btn-primary px-4'),
                                $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-outline-primary px-4'),
                            ), 'p-4 rounded-3 border mb-4'),
                        $account_card(lang('Private Dining Card'), 'bi-door-closed', lang('Your private dining enquiries'),
                            lang('A birthday, a wedding dinner or a company evening: follow the proposal and plan the menu with us in one place.'), array(
                                $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary px-4'),
                                $link(lang('Plan a private dinner'), '{{page:private_enquiry}}', 'btn btn-outline-primary px-4'),
                            ), 'p-4 rounded-3 border mb-4'),
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
    // The customer's invoices and returns from the ERP, under the account
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
            $mail_para(lang('Your account is ready. Your reservations, private dining enquiries and orders, and our messages about them, are all in one place.')),
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

    // The staff's article form: a Kitchen Journal article with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('rt-post-title', lang('Title'), pg_cf_with_role($input('rt-post-title', 'text', 'title', 'off'), 'title')),
            $field('rt-post-summary', lang('Summary'), pg_cf_with_role($textarea('rt-post-summary', 'summary', 3), 'description')),
            $field('rt-post-cover', lang('Cover Image'), pg_cf_with_role($file('rt-post-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('rt-post-content', lang('Content'), $textarea('rt-post-content', 'content', 14)),
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
                $col(array($field('rt-staff-name', lang('Full Name'), $input('rt-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('rt-staff-title', lang('Job Title'), $input('rt-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('rt-staff-department', lang('Department'), $input('rt-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('rt-staff-photo', lang('Photo'), $file('rt-staff-photo', 'photo', 'public', 'image/*'))), '6'),
                $col(array($field('rt-staff-email', lang('Email'), $input('rt-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('rt-staff-phone', lang('Phone'), $input('rt-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('rt-staff-bio', lang('A few words'), $textarea('rt-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory signed-in customers see.
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
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-3 bg-body-tertiary', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                            $portrait(lang('Selin Aras'), 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Selin Aras'), 'h6 fw-semibold mb-0', $bind('full_name')),
                            $para(lang('Head Chef'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Kitchen'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span('selin@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Ask me about the menu of the week, an allergy or a dish you would like to cook at home.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
