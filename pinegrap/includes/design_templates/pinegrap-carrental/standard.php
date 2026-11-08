<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", the pages every template carries: contact
 * and the inbox (a message becomes a conversation), the newsletter, the
 * Road Trip Journal (the blog) and its post page, the help center that
 * searches the whole site, the error page, the message and welcome
 * e-mails, the home page's newest posts and Google reviews, the newsletter
 * band at the foot of the pages, and the widgets that draw them. The
 * privacy page and the reply e-mail of the threads (email_reply) are
 * bookings.php's. Included after parts.php, mail.php and bookings.php in
 * the same scope (it reads $renter_name, $mail_reference and
 * $mail_new_account of bookings.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * The newsletter's sign-ups are its contact group (newsletter): the staff
 * write to it from the control panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$standard_pages = array();

// The contact form's topics and the sample message the views show.
$contact_topics = array(
    lang('A question before I book'),
    lang('Changing or cancelling a booking'),
    lang('My invoice or the deposit'),
    lang('Something I left in a car'),
    lang('A fine or a toll after my rental'),
    lang('Something else'),
);
$sample_message = lang('Hello, I returned the car on Sunday and the deposit is still held on my card. Could you check it, please?');
$standard_tel = 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']);

// Contact: a message to the company; the staff are told, the sender gets a
// reply. A booking or a business rental goes to its own form instead,
// which asks what the desk needs to answer it.
$standard_pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact Us'),
    'meta_description' => lang('A question before you book, a change to a booking, an invoice or the deposit? Write to the rental desk.'),
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        // What is sent opens the conversation page: its first message is
        // this one, and the answers follow as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact Us'), lang('A question before you book, a change to a booking, an invoice or something left in a car: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-success d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $el('span', '', lang('Text'), array(
                                $span(lang('Need a car? The booking request reaches the desk faster and tells us the dates and the class.') . ' ', '', lang('Text')),
                                $link(lang('Book a Car'), '{{page:book}}', 'alert-link'),
                            )),
                        )),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Head Office'), 'h5 fw-semibold mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span($company['address'], '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($company['phone'], $standard_tel, 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($company['email'], 'mailto:' . $company['email'], 'link-body-emphasis text-decoration-none'),
                            )),
                            $link(lang('All our desks and their hours'), '{{page:locations}}', 'fw-semibold link-primary text-decoration-none small'),
                        )),
                        $el('div', 'p-4 rounded-4 border mt-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('Follow the answer in your inbox'), '', lang('Text')),
                            )),
                            $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cr-contact', lang('contact, address, phone, e-mail, office, lost property'))),
    )),
);

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter contact group, and the subscriber gets the welcome e-mail.
$standard_pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New deals, new cars in the fleet and the newest Road Trip Journal posts, once a month in your inbox.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! Our next letter, with the new deals, is on its way to you.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to our newsletter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('New deals, new cars in the fleet and the newest Road Trip Journal posts, once a month in your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('In every letter'), 'h5 fw-semibold mb-3'),
                            $check_list(array(
                                lang('The deals of the month, before they are on the site'),
                                lang('New cars in the fleet'),
                                lang('A road trip worth a weekend, from the Road Trip Journal'),
                                lang('Never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The blog, called the Road Trip Journal: every post, newest first, with a
// search.
$standard_pages[] = array(
    'key'              => 'blog',
    'name'             => lang('road-trip-journal'),
    'folder'           => 'public',
    'title'            => lang('Road Trip Journal'),
    'meta_description' => lang('Roads worth a day of driving, coves and villages within an hour of our desks, and how to get the most from a rental car.'),
    'tree'             => $page(array(
        $page_header(lang('Road Trip Journal'), lang('Roads worth a day of driving, coves and villages within an hour of our desks, and how to get the most from a rental car.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
        $shared('booking_band'),
    )),
);

// A post: the post the list linked to (?r=), and its comments.
$standard_pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A post from the Road Trip Journal.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment on the blog'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments'), 'post_comments'),
        $shared('booking_band'),
    )),
);

// The inbox: the sender's conversations (for the staff, everybody's), and
// the conversation page, where both sides answer as comments.
$standard_pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('The messages you sent us from the contact page, and our answers.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('inbox_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-chat-left-text', 'text-primary'),
                                $span(lang('A question for the desk?'), '', lang('Text')),
                            )),
                            $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary rounded-pill'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Requests Note'), array(
                            $para(lang('Messages about a booking or a business rental are written under the request itself.'), 'small text-body-secondary mb-3'),
                            $link(lang('My Bookings'), '{{page:my_bookings}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);
$standard_pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    // The label is the lower-case noun key: the shared 'Reply' is a verb button.
    'comments' => array('label' => lang('reply'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_section(lang('Replies'), 'conversation_replies'),
    ), false),
);

// The help center: a search over the whole site - pages and the parts of
// pages marked as search sections (the desks, the extras, the deals, the
// terms, the questions) - with the common questions under it, each a
// search section of its own.
$help_topic = function ($icon_name, $text, $href) use ($el, $icon, $span) {
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Topic'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), array('href' => $href));
};
$standard_pages[] = array(
    'key'              => 'search',
    'name'             => lang('help-center'),
    'folder'           => 'public',
    'title'            => lang('Help Center'),
    'meta_description' => lang('Search the site: the fleet, the desks, extras, deals, rental terms, business rentals and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the fleet, the desks, the extras and deals, the rental terms and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'h5 fw-semibold mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-car-front', lang('Our Fleet'), '{{page:fleet}}'),
                                $help_topic('bi-geo-alt', lang('Locations'), '{{page:locations}}'),
                                $help_topic('bi-tag', lang('Deals'), '{{page:deals}}'),
                                $help_topic('bi-file-text', lang('Rental Terms'), '{{page:terms}}'),
                                $help_topic('bi-briefcase', lang('Business Rentals'), '{{page:business}}'),
                                $help_topic('bi-envelope', lang('Contact Us'), '{{page:contact}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Write to us from the contact page; the answer comes to your inbox on this site.'), 'small text-body-secondary mb-3'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary rounded-pill'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_header(lang('Questions'), lang('Frequently asked questions')),
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('How do I follow my booking request?'), lang('Signed in with the address you sent it from, open My Bookings: the request\'s page shows its status, the price we confirmed and our messages.'), lang('booking, status, my bookings, price'), 'cr-help-faq'),
                            $faq(2, lang('How do I change the dates of my booking?'), lang('Write under your booking request on its page; the desk answers there with the new price, and the status shows Confirmed again once the car is held.'), lang('change, dates, cancel, booking'), 'cr-help-faq'),
                            $faq(3, lang('When is my deposit released?'), lang('As soon as the car is back and checked; your bank shows it within a few working days. Ask under your booking if it takes longer.'), lang('deposit, card, refund, released'), 'cr-help-faq'),
                            $faq(4, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'cr-help-faq'),
                        ), array('id' => 'cr-help-faq')),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The site's error page.
$standard_pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page')), false),
);

// The contact form's e-mails: what the staff receive for a message, and the
// reply to the sender.
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_message_received',
    'name'  => lang('email-message-received'),
    'title' => lang('Message Received E-mail'),
    'tree'  => $mail_page(array($widget('email_message_sender'))),
));

// The newsletter's welcome, sent to a new subscriber.
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to our newsletter')),
        $mail_para(lang('Thank you for subscribing. Once a month you hear about our deals, the new cars in the fleet and a road trip worth a weekend.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See this month\'s deals'), '{{page:deals}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The list settings of the Road Trip Journal, on its page and on the home
// page.
$post_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'detail_page_id'      => '{{tab:blog_post}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No posts yet.'),
    ), $more);
};

$conversation_missing = lang('This conversation could not be found, or it is not yours to read.');
// The contact form's record settings: the conversation page's (the
// sender's own, and the staff's) and an e-mail page's (any reader: the
// e-mail is drawn for the message just sent).
$message_detail_config = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

$standard_widgets = array(

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-paper', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('Deals and road trips by e-mail'), 'h4 fw-semibold mb-2'),
            $para(lang('Leave your e-mail address; once a month we send the new deals and a road trip worth a weekend.'), 'text-body-secondary mb-4'),
            $newsletter_line('cr-newsletter-email'),
            $newsletter_consent('cr-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // The band at the foot of the pages: the same form, the newsletter
    // page's, so every sign-up lands in one list.
    'newsletter_band' => array(
        'page'   => '',
        'slug'   => lang('newsletter'),
        'config' => array(
            'regionType'          => 'custom_form',
            'form_source'         => 'existing',
            'form_id'             => '{{tab:newsletter}}',
            'custom_form_page_id' => '{{tab:newsletter}}',
        ),
        'tree'   => $widget_root(array(
            $el('section', 'py-5', lang('Newsletter'), array(
                $container(array(
                    $el('div', 'rounded-4 bg-body-tertiary p-4 p-lg-5', lang('Newsletter Band'), array(
                        $row(array(
                            $col(array(
                                $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                                    $icon('bi-signpost-2', 'fs-1 lh-1 text-primary'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('Deals and road trips by e-mail'), 'pcr-display h4 mb-1'),
                                        $para(lang('The new deals and a road trip worth a weekend, once a month. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '5'),
                            $col(array(
                                $newsletter_line('cr-band-email'),
                                $newsletter_consent('cr-band-consent'),
                                $captcha(),
                            ), '', '7'),
                        ), '4', '', 'align-items-center'),
                    )),
                )),
            )),
            $loop(array()),
        )),
    ),

    // Contact: the fields are the page's form.
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cr-contact-first-name', lang('First Name'), $input('cr-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cr-contact-last-name', lang('Last Name'), $input('cr-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cr-contact-email', lang('Email'), $input('cr-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cr-contact-phone', lang('Phone'), $input('cr-contact-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('cr-contact-topic', lang('What is it about?'), $select('cr-contact-topic', 'topic', lang('Choose a topic…'), $contact_topics)),
            $field('cr-contact-message', lang('Your Message'), $textarea('cr-contact-message', 'message', 6)),
            $agree('cr-contact-consent', 'consent', lang('I agree that the rental company keeps these details to answer me, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The hotel's rating and newest reviews on Google, under the sample
    // words of the home page. The widget draws nothing until a Places key
    // and the hotel's Place ID are saved, and this layout shows only while
    // Google returns reviews. Google asks for each review's author and its
    // link, and for "Google Maps" beside what is shown without a map.
    'google_reviews' => array(
        'page'   => 'home',
        'slug'   => lang('google-reviews'),
        'config' => array('regionType' => 'google_reviews'),
        'tree'   => $widget_root(array(
            $el('div', 'mt-5 pt-4 border-top', lang('Google Reviews'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Rating'), array(
                    $heading('h3', lang('Reviews on Google'), 'pcr-display h5 mb-0 me-2'),
                    $icon('bi-star-fill', 'text-warning'),
                    $span(lang('4.9'), 'fw-bold', lang('Average Rating'), $bind('__place_rating')),
                    $el('span', 'small text-body-secondary', lang('Number of ratings'), array(
                        $span('120', '', lang('Number'), $bind('__place_review_count')),
                        $span(' ' . lang('ratings'), '', lang('Label')),
                    )),
                )),
                $row(array($loop(array($col(array(
                    $el('article', 'd-flex flex-column h-100 p-4 rounded-4 border bg-body', lang('Review'), array(
                        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Author'), array(
                            $image('', '', 'rounded-circle flex-shrink-0 object-fit-cover', array('width' => '40', 'height' => '40',
                                '_attrs' => array($attr('referrerpolicy', 'no-referrer')),
                                '_bindings' => array('src' => '__review_author_photo', 'eo_visible_if' => 'review_has_photo'))),
                            $el('div', 'small', lang('Name and time'), array(
                                $link(lang('Author name'), '#', 'd-block fw-semibold text-body text-decoration-none', array(),
                                    array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_author_url', 'text' => '__review_author_name'))),
                                $span(lang('2 weeks ago'), 'text-body-secondary', lang('When'), $bind('__review_relative_time')),
                            )),
                        )),
                        $el('p', 'small mb-2', lang('Review rating'), array(
                            $icon('bi-star-fill', 'text-warning'),
                            $span('5', 'ms-1', lang('Rating'), $bind('__review_rating')),
                            $span('/5', '', lang('Out of')),
                        )),
                        $el('p', 'mb-3', lang('Review text'), array(), array('text' => lang('Review text'),
                            '_bindings' => array('text' => '__review_text', 'eo_visible_if' => 'review_has_text'))),
                        $link(lang('Read on Google Maps'), '#', 'small mt-auto', array(),
                            array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_url'))),
                    )),
                ), '6', '4')))), '4'),
                $el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 small', lang('Attribution'), array(
                    $link(lang('See all reviews on Google Maps'), '#', '', array(),
                        array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__place_maps_url'))),
                    $span('Google Maps', 'text-body-secondary notranslate', lang('Google attribution'),
                        array('_attrs' => array($attr('translate', 'no')), '_bindings' => array('text' => '__google_attribution'))),
                )),
            ), $show_when('has_reviews')),
        )),
    ),

    // The three newest posts on the home page.
    'home_posts' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $post_list_config(array('items_per_page' => 3, 'max_results' => 3)),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4'),
        )),
    ),

    // The Road Trip Journal: every post, newest first, with a search over
    // them.
    'blog_list' => array(
        'page'   => 'blog',
        'slug'   => lang('form-list'),
        'config' => $post_list_config(array(
            'items_per_page' => 9,
            'search_enabled' => true,
            'search_fields'  => array('title', 'summary', 'content'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the Road Trip Journal...'),
        )),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One post, with the way to book a car for the trip it tells of.
    'blog_post' => array(
        'page'   => 'blog_post',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This post could not be found. It may have been removed.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-journal-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Post not found'), 'h3 fw-semibold mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the Road Trip Journal'), '{{page:blog}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('Road Trip Journal'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $heading('h1', lang('Five coast roads worth a day of driving'), 'pcr-display display-6 mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                // The author line leaves when the record has no contact name.
                                $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-2 ms-2', lang('Author'), array(
                                    $icon('bi-person'),
                                    $span($team[0]['name'], 'pg-field-value', lang('Value'), $bind('submitter_name')),
                                )),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-key'),
                                $span(lang('Want to drive it yourself? Collect a car at the airport or in town.'), '', lang('Text')),
                                $link(lang('Book a Car'), '{{page:book}}', 'alert-link'),
                            ), array('_attrs' => array($attr('role', 'note')))),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // A post's comments: the post page's own Comments widget, under the
    // post view (whose record the comments belong to). Oldest first, an
    // answer under the comment it answers and one more level under that; a
    // featured comment comes first. Twenty comments with their answers to a
    // page, each a permalink.
    'post_comments' => array(
        'page'   => 'blog_post',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'first',
            'layout'     => 'threaded',
            'max_depth'  => 2,
            'per_page'   => 20,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Comments'),
            'empty'     => lang('No comments yet. Be the first to share your thoughts.'),
            'heading'   => lang('Leave a comment'),
            'label'     => lang('Your comment'),
            'sign_in'   => lang('Sign in to add your comment.'),
            'submit'    => lang('Post comment'),
            'watch_box' => lang('Notify me when a new comment is added.'),
            'follow'    => lang('Notify me of new comments'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new comments'),
            'reopen'    => lang('Allow new comments'),
            'reply'     => true,
            'watch'     => true,
        )),
    ),

    // The inbox: conversations the sender started (a signed-in submission
    // of the contact form), or is watching; the staff see them all. The
    // contact form's subject is its topic.
    'inbox_list' => array(
        'page'   => 'inbox',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:contact}}',
            'detail_page_id'          => '{{tab:conversation}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span($contact_topics[0], 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                            $span(lang('Date'), 'small text-body-secondary text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text'),
                            $span('0', 'fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
                            $span(lang('replies'), '', lang('Label')),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // One conversation: the first message; the answers follow as comments.
    'conversation' => $message_detail_config('conversation', $conversation_missing, 'submitter_only') + array(
        'tree' => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-semibold mt-3'),
                            $para($conversation_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.'), '', lang('Text')),
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
                                    $span(lang('Inbox'), '', lang('Link Text')),
                                ), array('href' => '{{page:inbox}}')),
                                $edit_button(),
                            )),
                            $heading('h1', $contact_topics[0], 'h3 fw-semibold mb-1', $bind('topic')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array_merge(
                                    array($icon('bi-person-circle', 'fs-5 text-primary')), $renter_name())),
                                $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The answers in a conversation: the conversation page's own Comments
    // widget, which shows them only to whoever may read the conversation
    // above it. Oldest first, one after another, all on one page, never
    // pinned. No Reply, Share or follow box: the conversation has two sides,
    // its sender is e-mailed every answer and the staff every new message.
    'conversation_replies' => array(
        'page'   => 'conversation',
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
            'title'     => lang('Replies'),
            'empty'     => lang('No replies yet. Our answer will appear here.'),
            'heading'   => lang('Write a reply'),
            'label'     => lang('Your reply'),
            'sign_in'   => lang('Sign in to reply.'),
            'submit'    => lang('Send reply'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new replies'),
            'reopen'    => lang('Allow new replies'),
            'reply'     => false,
            'watch'     => false,
        )),
    ),

    // The help center's search: the whole site for ?query=, ten results a
    // page. A result that matches a search section links to that part of
    // its page (^^__result_section^^ names it).
    'site_search' => array(
        'page'   => 'search',
        'slug'   => lang('search'),
        'config' => array(
            'regionType'       => 'search_results',
            'search_scope'     => 'all',
            'results_per_page' => 10,
            'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
        ),
        'tree'   => $widget_root(array(
            $el('form', 'mb-4', lang('Search Form'), array(
                $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                    $el('input', 'form-control', '', array(), array(
                        '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the site…')), $attr('aria-label', lang('Search the site'))),
                        '_bindings' => array('value' => 'search_query'),
                    )),
                    $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                        '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                        '_bindings' => array('action' => 'submit_search'),
                    )),
                )),
            ), array('_bindings' => array('action' => 'search_form'))),
            $para(lang('Results heading'), 'fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
            $el('div', '', lang('Results'), array(
                $loop(array(
                    $el('article', 'py-3 border-bottom position-relative', lang('Result Row'), array(
                        $span(lang('Page'), 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                        $el('h2', 'h5 fw-semibold mb-1 position-static', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $para(lang('Deals'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                        $para(lang('Excerpt'), 'small text-body-secondary mb-0', $bind('__result_excerpt')),
                    )),
                )),
            )),
            $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
        )),
    ),

    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),

    // The contact form's e-mails: drawn for the message just sent.
    'email_message_admin' => $message_detail_config('email_new_message', lang('The message could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new message has arrived')),
            $mail_para(lang('It came from the contact form. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
            $mail_box(array(
                $mail_line(lang('Name'), $renter_name()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Subject'), array($span($contact_topics[0], '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_message_sender' => $message_detail_config('email_message_received', lang('The message could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('Your message has reached us')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => $contact_topics[0], '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
