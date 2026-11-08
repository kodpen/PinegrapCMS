<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", the pages every template carries: contact and
 * the inbox (a message becomes a conversation), the newsletter, Health
 * Advice (the blog, filtered by ?topic=) and its article page, the help
 * page that searches the whole site, the error page, the message and
 * newsletter e-mails, and the widgets that draw them, the home page's
 * articles and Google reviews among them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An article's topic is a pick list of the post form (the staff page,
 * blog_new) whose answers are the health topics of parts.php, so a link
 * with ?topic= filters Health Advice by the words a visitor sees, and the
 * article's "Ask a pharmacist" link carries its topic into the question.
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

// The opening hours as lines of the contact card.
$contact_hours = array();
foreach ($opening_hours as $h) {
    $contact_hours[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
        $span($h['days'], '', lang('Days')),
        $span($h['hours'], 'prx-price fw-semibold', lang('Hours')),
    ));
}

// Contact: a message to the pharmacy; the staff are told, the sender gets
// a reply. A prescription or a health question has its own request page,
// which asks what the pharmacist needs.
$standard_pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question about an order, home delivery or the pharmacy? Write to us, and the answer comes to your inbox.'),
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
        $page_header(lang('Contact us'), lang('A question about an order, home delivery or the pharmacy: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-secondary d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('A prescription or a question about your health? Its own request is faster: it asks what the pharmacist needs, and you follow it from your account.'), '', lang('Text')),
                        )),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('The pharmacy'), 'prx-display h5 mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span($pharmacy['address'], '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($pharmacy['phone'], $tel($pharmacy['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($pharmacy['email'], 'mailto:' . $pharmacy['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                            $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                            $el('ul', 'list-unstyled small mb-4', lang('Opening Hours'), $contact_hours),
                            $heading('h3', lang('Prescriptions and questions'), 'h6 fw-bold mb-2'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Send a Prescription Request'), '{{page:prescription}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                                $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                                $link(lang('Find the pharmacy on duty'), '{{page:on_duty}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                            )),
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
        )),
    )),
);

// The newsletter: the page owns the form. What is sent adds the address
// to the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$standard_pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('Seasonal reminders, new services, health check days and the newest Health Advice, once or twice a month.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are on our newsletter list.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to our newsletter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('Seasonal reminders, new services, health check days and the newest Health Advice, once or twice a month.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('What we send'), 'prx-display h5 mb-3'),
                            $check_list(array(
                                lang('Reminders for the flu season and travel vaccinations'),
                                lang('Health check days and new services'),
                                lang('The newest Health Advice: tips from our pharmacists'),
                                lang('Once or twice a month, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The links above Health Advice that narrow it: one per health topic,
// each opening the list with the filter set (?topic=).
$topic_bar = array($span(lang('Topic'), 'small fw-semibold text-body-secondary me-1', lang('Label')),
    $link(lang('All'), '{{page:blog}}', 'btn btn-sm btn-outline-secondary rounded-pill'));
foreach ($topic_titles as $t) {
    $topic_bar[] = $link($t, '{{page:blog}}?' . $query(array('topic' => $t)), 'btn btn-sm btn-outline-secondary rounded-pill');
}

// Health Advice: every article, newest first, filtered by the address
// (?topic=), with a search.
$standard_pages[] = array(
    'key'              => 'blog',
    'name'             => lang('health-advice'),
    'folder'           => 'public',
    'title'            => lang('Health Advice'),
    'meta_description' => lang('Advice from our pharmacists: medicines, heart and blood pressure, diabetes, skin and sun, children\'s health and travel.'),
    'tree'             => $page(array(
        $page_header(lang('Health Advice'), lang('Advice from our pharmacists: medicines, heart and blood pressure, diabetes, skin and sun, children\'s health and travel.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array(
                $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $topic_bar,
                    array('_attrs' => array($attr('aria-label', lang('Topic'))))),
                $widget('blog_list'),
            )),
        )),
    )),
);

// A post: the one the list linked to (?r=), and its comments.
$standard_pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('health-advice-article'),
    'folder'           => 'public',
    'title'            => lang('Health Advice Article'),
    'meta_description' => lang('An article from Health Advice.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment in Health Advice'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments'), 'post_comments'),
    )),
);

// The inbox: the customer's conversations (for the staff, everybody's), and
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
                                $span(lang('A question for us?'), '', lang('Text')),
                            )),
                            $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary rounded-pill'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Requests Note'), array(
                            $para(lang('Prescription requests and questions to the pharmacist are followed on their own pages, from your account.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('My Prescriptions'), '{{page:my_prescriptions}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                                $link(lang('My Questions'), '{{page:my_questions}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                            )),
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

// Help: a search over the whole site - pages and the parts of pages marked
// as search sections (the services, the health checks, being on duty and
// the questions) - with the common questions under it, each a search
// section of its own.
$help_topic = function ($icon_name, $text, $href) use ($el, $icon, $span) {
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Topic'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), array('href' => $href));
};
$standard_pages[] = array(
    'key'              => 'search',
    'name'             => lang('help'),
    'folder'           => 'public',
    'title'            => lang('Help'),
    'meta_description' => lang('Search the site: the services and health checks, prescriptions, the pharmacy on duty, the team, getting here and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help'), lang('Search the whole site: the services and health checks, prescriptions and home delivery, the pharmacy on duty, the team, getting here, and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'prx-display h5 mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-grid', lang('Our Services'), '{{page:services}}'),
                                $help_topic('bi-heart-pulse', lang('Health Checks'), '{{page:health_checks}}'),
                                $help_topic('bi-prescription2', lang('Send a Prescription Request'), '{{page:prescription}}'),
                                $help_topic('bi-moon-stars', lang('Find the pharmacy on duty'), '{{page:on_duty}}'),
                                $help_topic('bi-people', lang('Our Pharmacists'), '{{page:team}}'),
                                $help_topic('bi-geo-alt', lang('Getting Here'), '{{page:location}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'prx-display h5 mb-2'),
                            $para(lang('A question about a medicine or your health goes to a pharmacist; for anything else, write to us from the contact page.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-sm btn-primary rounded-pill'),
                                $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                            )),
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
                            $faq(1, lang('Can I collect my medicines without the original prescription?'), lang('No. We prepare them as soon as your request arrives, but we hand them over only when the original prescription is shown at the counter or at the door.'), lang('prescription, original, collect, hand over'), 'rx-help-faq'),
                            $faq(2, lang('How do I know my prescription is ready?'), lang('Its status in My Prescriptions changes to Ready to collect or Out for delivery, and you get a message by e-mail at the same time.'), lang('ready, status, message, collect, delivery'), 'rx-help-faq'),
                            $faq(3, lang('Where do I go when the pharmacy is closed?'), lang('To the pharmacy on duty: our on-duty page tells you how to find it tonight. In an emergency, call 112 at once.'), lang('closed, night, holiday, on duty, emergency'), 'rx-help-faq'),
                            $faq(4, lang('When is the pharmacy open?'), sprintf(lang('Monday to Friday %1$s, Saturday %2$s, Sunday: %3$s.'), $opening_hours[0]['hours'], $opening_hours[1]['hours'], $opening_hours[2]['hours']), lang('opening hours, open, closed, weekend, early, late'), 'rx-help-faq'),
                            $faq(5, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'rx-help-faq'),
                        ), array('id' => 'rx-help-faq')),
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

// The contact form's notification: what the staff receive for a message.
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
// The contact form's reply to the sender.
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
        $mail_para(lang('Thank you for joining. You hear first about the flu season, health check days and new services, with the newest Health Advice from our pharmacists.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('Read Health Advice'), '{{page:blog}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The sample message of the contact form, its conversation and e-mails.
$sample_topic = lang('A question about home delivery');
$sample_message = lang('Hello, my mother cannot leave the house at the moment. Do you deliver to Park Street, and can she pay at the door?');

$standard_widgets = array(

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-paper', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('Join our newsletter'), 'prx-display h4 mb-2'),
            $para(lang('Leave your e-mail address; we write before the flu season, when there is a health check day or a new service.'), 'text-body-secondary mb-4'),
            $newsletter_line('rx-newsletter-email'),
            $newsletter_consent('rx-newsletter-consent'),
            $captcha(),
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
                $col(array($field('rx-contact-first-name', lang('First Name'), $input('rx-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('rx-contact-last-name', lang('Last Name'), $input('rx-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('rx-contact-email', lang('Email'), $input('rx-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('rx-contact-phone', lang('Phone'), $input('rx-contact-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('rx-contact-topic', lang('What is it about?'), $select('rx-contact-topic', 'topic', lang('Choose a topic…'), array(
                $sample_topic,
                lang('An order from the shop'),
                lang('Opening hours'),
                lang('Working with us'),
                lang('Something else'),
            ))),
            $field('rx-contact-message', lang('Your Message'), $textarea('rx-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message'), 'btn btn-primary rounded-pill px-4'),
            $loop(array()),
        )),
    ),

    // The pharmacy's rating and newest reviews on Google, beside the sample
    // words of the home page. The widget draws nothing until a Places key
    // and the pharmacy's Place ID are saved, and this layout shows only while
    // Google returns reviews. Google asks for each review's author and its
    // link, and for "Google Maps" beside what is shown without a map.
    'google_reviews' => array(
        'page'   => 'home',
        'slug'   => lang('google-reviews'),
        'config' => array('regionType' => 'google_reviews'),
        'tree'   => $widget_root(array(
            $el('div', 'mt-5 pt-4 border-top', lang('Google Reviews'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Rating'), array(
                    $heading('h3', lang('Reviews on Google'), 'prx-display h5 mb-0 me-2'),
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

    // The three newest articles on the home page.
    'home_posts' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 3,
            'max_results'         => 3,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4'),
        )),
    ),

    // Health Advice: every article, newest first, filtered by the address
    // (?topic=: the article's topic), with a search over them.
    'blog_list' => array(
        'page'   => 'blog',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 9,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('title', 'summary', 'content'),
            'search_width'        => 'full',
            'filters'             => array(array('field' => 'topic', 'operator' => 'is equal to', 'value' => '',
                                             'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'topic')),
            'search_label'        => lang('Search Health Advice...'),
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One post.
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
                            $heading('h2', lang('Post not found'), 'prx-display h3 mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to Health Advice'), '{{page:blog}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('Health Advice'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $para($topic_titles[0], 'prx-eyebrow text-uppercase fw-semibold text-primary mb-2', $bind('topic')),
                            $heading('h1', lang('Five questions to ask about a new medicine'), 'prx-display display-6 mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                // The author line leaves when the record has no contact name.
                                $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-2 ms-2', lang('Author'), array(
                                    $icon('bi-person'),
                                    $span(lang('The pharmacy team'), 'pg-field-value', lang('Value'), $bind('submitter_name')),
                                )),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            // The question link carries the article's topic (?topic=).
                            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-chat-left-dots'),
                                $span(lang('General advice, not a diagnosis: ask a pharmacist about your own case.'), '', lang('Text')),
                                $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'alert-link', array(), array(
                                    '_bindings'   => array('href' => '__page_link'),
                                    '_bindParams' => array('href' => array('page_id' => '{{tab:ask}}', 'params' => array(array('name' => 'topic', 'token' => 'topic')))),
                                )),
                            )),
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

    // The inbox: conversations the customer started (a signed-in submission
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
                            $span($sample_topic, 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
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
    'conversation' => array(
        'page'   => 'conversation',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Conversation not found'), 'prx-display h3 mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
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
                                $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-pill', array(),
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
                            $heading('h1', $sample_topic, 'prx-display h3 mb-1', $bind('topic')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array(
                                    $icon('bi-person-circle', 'fs-5 text-primary'),
                                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                )),
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

    // Help's search: the whole site for ?query=, ten results a page.
    // A result that matches a search section links to that part of its
    // page (^^__result_section^^ names it).
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
                    $el('button', 'btn btn-primary rounded-pill px-4', lang('Search Button'), array($icon('bi-search')), array(
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
                        $para(lang('Our Services'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
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
                $mail_line(lang('Sender'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', '', lang('Text')),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Subject'), array($span($sample_topic, '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0')),
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
            $mail_heading(lang('Your message has reached us')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => $sample_topic, '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $sample_message), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password.
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
);

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
