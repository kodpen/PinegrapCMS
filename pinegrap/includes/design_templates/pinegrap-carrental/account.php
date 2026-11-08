<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", the account and staff part: signing in, up
 * and out, My Account and its pages, the staff directory, the staff area
 * (the way to the booking requests and the business enquiries, the fleet's
 * car form, the Road Trip Journal's post form with its first posts, the
 * directory's form), the account e-mails, and the widgets that draw them
 * (the account bar's login region and My Account included). Included after
 * parts.php and mail.php in the same scope; returns
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
// shop is on (the address book is a shop page; the rental itself is paid
// at the desk).
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

// The renter's own pages, behind the registration folder: the overview
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

// The company's team as signed-in renters see it: the staff's own list,
// kept from the staff area.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The rental desks and the office, which desk each of them works at and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the welcome with the way to the booking
// requests and the business enquiries, then the staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary text-decoration-none link-body-emphasis pcr-lift', $title, array(
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
                        $heading('h1', lang('Welcome to the staff area'), 'pcr-display display-6 fw-bold mb-3'),
                        $para(lang('The booking requests, the business enquiries, the fleet, the conversations and the Road Trip Journal are kept here. Open a request, answer with the price and press Change Status when the car is held; what you write under it reaches the renter by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Booking Requests'), '{{page:staff_bookings}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Business Enquiries'), '{{page:staff_enquiries}}', 'btn btn-outline-primary rounded-pill px-4'),
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
                    $staff_shortcut('bi-calendar2-check', lang('Booking Requests'), lang('Every booking request, newest first. Open one to send the price, hold the car or offer another class.'), '{{page:staff_bookings}}'),
                    $staff_shortcut('bi-briefcase', lang('Business Enquiries'), lang('Every business and long-term rental enquiry, newest first. Send an offer and keep its status up to date.'), '{{page:staff_enquiries}}'),
                    $staff_shortcut('bi-car-front', lang('New Car'), lang('Add a car to the fleet with its photos, seats and daily rate; it appears in its class at once.'), '{{page:car_new}}'),
                    $staff_shortcut('bi-journal-text', lang('New Article'), lang('Write for the Road Trip Journal; the post appears there as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-person-lines-fill', lang('Add to the Staff Directory'), lang('Add a colleague to the directory renters see.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The Road Trip Journal's first posts, written into its form when the form
// is made: the template carries them, so they are there on every site.
// Each is edited or deleted like any other post. None names a date: the
// post's date is the record's own.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('Five coast roads worth a day of driving'),
        'summary'     => lang('Bays, olive groves and small harbours: the roads along the Aegean that are worth a slow day behind the wheel.'),
        'content'     => $post_body(array(
            lang('Leave early, before the day trippers. A coast road is at its best in the morning light, with the sea on one side and nobody behind you.'),
            lang('Plan one long stop for lunch in a harbour town and a few short ones at the viewpoints; a coast road is not a road to hurry on.'),
            lang('Fill the tank before you leave the city. Fuel stations are far apart on the quieter stretches, and the views are better without a worry.'),
        )),
        'cover_image' => $photo('post-1', 1200, 675),
    ),
    array(
        'title'       => lang('Which car class suits your trip?'),
        'summary'     => lang('How many of you travel, how much luggage you carry and where you drive decide it; here is how to choose.'),
        'content'     => $post_body(array(
            lang('An economy car is right for two people with light luggage in the city: easy to park and light on fuel.'),
            lang('A family of four with suitcases needs a mid-size car or an estate; count the bags as well as the seats.'),
            lang('For mountain roads or a gravel track to a beach, choose an SUV. For a group or a lot of gear, a van saves a second car.'),
        )),
        'cover_image' => $photo('post-2', 1200, 675),
    ),
    array(
        'title'       => lang('Picking up your rental car: a short checklist'),
        'summary'     => lang('Your licence, a card in your own name and five minutes around the car make every pick-up quick and every return easy.'),
        'content'     => $post_body(array(
            lang('Bring the driving licence of every driver and a credit card in the main driver\'s name; the deposit is held on it.'),
            lang('Walk around the car with our staff before you leave and note every scratch on the form. Photos on your phone help too.'),
            lang('Check the fuel level and how to return it, and save the desk\'s phone number before you drive away.'),
        )),
        'cover_image' => $photo('post-3', 1200, 675),
    ),
    array(
        'title'       => lang('Driving with children: what to bring and what to ask for'),
        'summary'     => lang('The right child seat, snacks within reach and a few planned stops keep a family road trip happy.'),
        'content'     => $post_body(array(
            lang('Ask for a child seat or a booster when you book, with the child\'s age, so the right one is fitted when you arrive.'),
            lang('Keep water, snacks and something to play with within reach, not in the boot.'),
            lang('Stop every two hours for a walk; children travel better, and so does the driver.'),
        )),
        'cover_image' => $photo('post-4', 1200, 675),
    ),
);

// The staff's forms: the Road Trip Journal and the staff directory.
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
    lang('Write the post; it appears in the Road Trip Journal as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Road Trip Journal'),
        'collection'           => 1,
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample posts are written in when the form is made,
        // so the Road Trip Journal and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
        // The cover is the posts' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$account_pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in renters see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'collection'           => 1,
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// ── Account e-mails ─────────────────────────────────────────────────────

// The welcome after a sign-up and the password reset link. Their Account
// E-mail widgets fill in the renter's name and links.
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

// The renter's account menu: the booking requests and the business
// enquiries first.
$account_links = array(
    $account_link('bi-calendar2-check', lang('My Bookings'), '{{page:my_bookings}}'),
    $account_link('bi-briefcase', lang('My Enquiries'), '{{page:my_enquiries}}'),
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
$request_card = function ($name, $icon_name, $title, $text, $buttons, $class) use ($el, $heading, $para, $icon) {
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
    // renter's menu.
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
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Bookings'), '{{page:my_bookings}}'),
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

    // My Account: the renter's card and menu beside the booking, business
    // and fleet cards and the orders (drawn only for a member who has any).
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
                            $heading('h1', lang('Jane Cooper'), 'pcr-display h4 fw-semibold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $request_card(lang('Booking Requests Card'), 'bi-calendar2-check', lang('Your booking requests'),
                            lang('Follow your request until the car is held, read the price we send and write to the rental desk.'), array(
                                $link(lang('My Bookings'), '{{page:my_bookings}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Book a Car'), '{{page:book}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $request_card(lang('Business Card'), 'bi-briefcase', lang('Business and long-term rentals'),
                            lang('See the offers for your company\'s cars, or ask about a car for a month or more.'), array(
                                $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Business Rentals'), '{{page:business}}', 'btn btn-outline-primary rounded-pill px-4'),
                            ), 'p-4 rounded-4 border mb-4'),
                        $request_card(lang('Fleet Card'), 'bi-car-front', lang('Your next trip'),
                            lang('Choose a class from the fleet, or start from one of this season\'s deals.'), array(
                                $link(lang('Our Fleet'), '{{page:fleet}}', 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Deals'), '{{page:deals}}', 'btn btn-outline-primary rounded-pill px-4'),
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
            $mail_account_greeting(),
            $mail_para(lang('Your account is ready. Your booking requests, your business enquiries and our messages about them are all in one place.')),
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

    // The staff's post form: a Road Trip Journal post with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('cr-post-title', lang('Title'), pg_cf_with_role($input('cr-post-title', 'text', 'title', 'off'), 'title')),
            $field('cr-post-summary', lang('Summary'), pg_cf_with_role($textarea('cr-post-summary', 'summary', 3), 'description')),
            $field('cr-post-cover', lang('Cover Image'), pg_cf_with_role($file('cr-post-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('cr-post-content', lang('Content'), $textarea('cr-post-content', 'content', 14)),
            $submit(lang('Publish'), 'btn btn-primary rounded-pill px-4'),
            $loop(array()),
        )),
    ),

    // The staff page's directory form: one person and the desk they work at.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cr-staff-name', lang('Full Name'), $input('cr-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('cr-staff-title', lang('Job Title'), $input('cr-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('cr-staff-department', lang('Rental desk'), $input('cr-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('cr-staff-photo', lang('Photo'), $file('cr-staff-photo', 'photo', 'public', 'image/*'))), '6'),
                $col(array($field('cr-staff-email', lang('Email'), $input('cr-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('cr-staff-phone', lang('Phone'), $input('cr-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('cr-staff-bio', lang('A few words'), $textarea('cr-staff-bio', 'bio', 3, false)),
            $submit(lang('Add'), 'btn btn-primary rounded-pill px-4'),
            $loop(array()),
        )),
    ),

    // The directory signed-in renters see.
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
            'search_label'        => lang('Search by name or desk...'),
            'empty_message'       => lang('The directory is empty for now.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                            $portrait(lang('Deniz Kaya'), 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Deniz Kaya'), 'h6 fw-semibold mb-0', $bind('full_name')),
                            $para(lang('Rental Desk Supervisor'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Rental desk'), array(
                                $icon('bi-geo-alt', 'text-primary me-1'),
                                $span(lang('Airport desk'), 'pg-field-value', lang('Value'), $bind('department')),
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
                                $span(lang('Ask me about a pick-up at the airport, a child seat or a car for the whole summer.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
