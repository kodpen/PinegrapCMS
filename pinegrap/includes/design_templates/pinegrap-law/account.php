<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Law Firm", the account and staff part: signing in, up
 * and out, My Account and its pages, the staff directory, the staff area
 * (the newest consultation requests, the Insights form with its first
 * posts, the directory's form), the account e-mails, and the widgets that
 * draw them (the account bar's login region and My Account included).
 * Included after parts.php, mail.php and consultation.php in the same scope
 * (the staff area's list draws consultation.php's consultation row);
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

// The firm's team as signed-in clients see them: the staff's own list,
// kept from the staff area.
$account_pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The lawyers, the paralegals and the office, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// ── The staff area ──────────────────────────────────────────────────────

// Behind the private folder: the newest consultation requests first, then the
// staff's shortcuts.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-3 bg-body-tertiary text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
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
        $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('Staff only'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'display-6 fw-semibold mb-3'),
                        $para(lang('The consultation requests, the applications and the conversations are kept here. Check each request for a conflict, then open it and press Change Status; what you write under it reaches the client by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('All Consultations'), '{{page:staff_consultations}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Consultations'), array(
            $container(array(
                $section_header(lang('Newest first'), lang('Consultation Requests'), lang('All Consultations'), '{{page:staff_consultations}}'),
                $widget('staff_consultations_new'),
            )),
        )),
        $el('section', 'pb-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-person-workspace', lang('Applications'), lang('Every application from the careers page, with its CV. Answer below each one.'), '{{page:staff_applications}}'),
                    $staff_shortcut('bi-chat-dots', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-journal-plus', lang('New Insight'), lang('Write a post; it appears under Insights as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory clients see.'), '{{page:staff_entry}}'),
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
        'title'       => lang('What a shareholders agreement should say'),
        'summary'     => lang('Five clauses that save a company from its founders\' first disagreement.'),
        'content'     => $post_body(array(
            lang('Who decides what: list the decisions that need every shareholder, and leave the rest to the board.'),
            lang('What happens when someone leaves: a fair price and a clear way to buy the shares back.'),
            lang('How a deadlock ends: mediation first, then a buy-out clause both sides agreed to in calmer days.'),
        )),
        'cover_image' => $photo('post-1', 1200, 675),
    ),
    array(
        'title'       => lang('Dismissal: what an employer must do first'),
        'summary'     => lang('Notice, a written reason and the right to answer: the steps a court looks for.'),
        'content'     => $post_body(array(
            lang('Write the reason down, and give the employee the chance to answer it before the decision.'),
            lang('Count the notice period from the contract and the length of service, and pay what is owed on the last day.'),
            lang('Mediation comes before the labour court: a fair offer there often ends the matter.'),
        )),
        'cover_image' => $photo('post-2', 1200, 675),
    ),
    array(
        'title'       => lang('Before you buy a property: four checks'),
        'summary'     => lang('The title deed, the zoning, the debts on it and the building permit, checked before the deposit.'),
        'content'     => $post_body(array(
            lang('Read the title deed record: who owns it, and whether a mortgage or a seizure is written on it.'),
            lang('Ask the municipality for the zoning and the building permit; a flat without them is a risk.'),
            lang('Pay the deposit only against a written preliminary contract, signed before a notary.'),
        )),
        'cover_image' => $photo('post-3', 1200, 675),
    ),
    array(
        'title'       => lang('Writing a will that holds'),
        'summary'     => lang('The forms a will can take, the shares the law keeps for the family, and where to keep it.'),
        'content'     => $post_body(array(
            lang('A will written before a notary is the hardest to challenge; a handwritten one must be written, dated and signed by hand.'),
            lang('Part of the estate is kept by law for the closest family; a will can share out only the rest freely.'),
            lang('Tell someone you trust where the will is kept, and read it again after every great change in the family.'),
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
                            $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$account_pages[] = $staff_form_page('blog_new', lang('new-insight'), lang('New Insight'),
    lang('Write the post; it appears under Insights as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Insights'),
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

// The client's account menu: the consultations first.
$account_links = array(
    $account_link('bi-folder2-open', lang('My Consultations'), '{{page:my_consultations}}'),
    $account_link('bi-calendar-plus', lang('Book a Consultation'), '{{page:consultation}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// The staff area's newest consultation requests: the consultation part's
// staff list on this page, the last ten without the search.
$staff_consultations_new = array(
    'page'   => 'staff',
    'slug'   => lang('form-list'),
    'config' => array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:consultation}}',
        'detail_page_id'      => '{{tab:consultation_view}}',
        'items_per_page'      => 10,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No consultation requests yet.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'list-group', lang('Consultation List'), array(
            $loop(array($consultation_row(true))),
        )),
    )),
);

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
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
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
                    $login_page_item(lang('My Consultations'), '{{page:my_consultations}}'),
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

    // My Account: the member's card and menu beside the consultations
    // card. No orders: the firm sells nothing online.
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
                        $el('div', 'p-4 rounded-3 border mb-4', lang('Consultations Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
                                $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                                    $icon('bi-briefcase', 'fs-2 text-primary'),
                                    $el('div', '', lang('Text'), array(
                                        $heading('h2', lang('Your matters'), 'h5 fw-semibold mb-1'),
                                        $para(lang('Follow your consultation requests, send documents and read your lawyer\'s messages.'), 'small text-body-secondary mb-0'),
                                    )),
                                )),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                    $link(lang('My Consultations'), '{{page:my_consultations}}', 'btn btn-primary px-4'),
                                    $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-outline-primary px-4'),
                                )),
                            )),
                        )),
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('Inbox Card'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('A question that is not about a matter?'), '', lang('Text')),
                            )),
                            $para(lang('Fees, invoices or anything else: write from the contact page, and the answer comes to your inbox.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

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
            $mail_para(lang('Your account is ready. Your consultations, your documents and your lawyer\'s messages are all in one place.')),
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

    // The staff's Insights form: a post with its cover.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('lw-post-title', lang('Title'), pg_cf_with_role($input('lw-post-title', 'text', 'title', 'off'), 'title')),
            $field('lw-post-summary', lang('Summary'), pg_cf_with_role($textarea('lw-post-summary', 'summary', 3), 'description')),
            $field('lw-post-cover', lang('Cover Image'), pg_cf_with_role($file('lw-post-cover', 'cover_image', 'public'), 'media')),
            $field('lw-post-content', lang('Content'), $textarea('lw-post-content', 'content', 14)),
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
                $col(array($field('lw-staff-name', lang('Full Name'), $input('lw-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('lw-staff-title', lang('Job Title'), $input('lw-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('lw-staff-department', lang('Department'), $input('lw-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('lw-staff-photo', lang('Photo'), $file('lw-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('lw-staff-email', lang('Email'), $input('lw-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('lw-staff-phone', lang('Phone'), $input('lw-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('lw-staff-bio', lang('A few words'), $textarea('lw-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory signed-in clients see.
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
                            $portrait(lang('Deniz Aksoy'), 128), lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Deniz Aksoy'), 'h6 fw-semibold mb-0', $bind('full_name')),
                            $para(lang('Office Manager'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Office'), 'pg-field-value', lang('Value'), $bind('department')),
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
                                $span(lang('Ask me about appointments, documents and invoices.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),
);

return array('pages' => $account_pages, 'widgets' => $account_widgets);
