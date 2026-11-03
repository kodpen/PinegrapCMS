<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site", the pages every template carries:
 * contact and the inbox (a message becomes a conversation), the newsletter
 * as the launch list, the Newsroom (the blog) and its release page, the
 * help center that searches the whole site, the error page, the message
 * and launch list e-mails, and the widgets that draw them. Included after
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

$standard_pages = array();

// Contact: a message to the maker; the staff are told, the sender gets a
// reply. The press and resellers write here too; a trade-in or a repair
// has its own request page.
$standard_pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question about an order, the press or selling Orbit? Write to us, and the answer comes to your inbox.'),
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
        $page_header(lang('Contact us'), lang('A question about an order, the press or selling Orbit: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-warning d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('A phone to repair or to trade in? Its own request is faster: it asks what we need and you follow it from your account.'), '', lang('Text')),
                        )),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Our office'), 'h5 fw-semibold mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-4', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $heading('h3', lang('Repairs and trade-ins'), 'h6 fw-semibold mb-2'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Repair and Support'), '{{page:support}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                                $link(lang('Trade-in'), '{{page:tradein}}', 'btn btn-sm btn-outline-primary rounded-pill'),
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

// The launch list: the newsletter page owns the form. What is sent adds
// the address to the launch list's contact group, and the subscriber gets
// the welcome e-mail. Without the shop the buy page's cards lead here.
$standard_pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('launch-list'),
    'folder'           => 'public',
    'title'            => lang('Launch List'),
    'meta_description' => lang('Hear first: new models, updates, offers and when a colour is back in stock.'),
    'form'             => array(
        'form_name'            => lang('Launch List'),
        'confirmation_message' => lang('Thank you! You are on the launch list.'),
        'contact_group_id'     => '{{contact_group:launch_list}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the launch list'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Launch List'), lang('Hear first: new models, updates, offers and when a colour is back in stock.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('What we send'), 'h5 fw-semibold mb-3'),
                            $check_list(array(
                                lang('A new model or colour, the day it is announced'),
                                lang('What each software update brings'),
                                lang('Offers and trade-in credits for the people on the list first'),
                                lang('A few e-mails a year, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The Newsroom: every press release, newest first, with a search.
$standard_pages[] = array(
    'key'              => 'blog',
    'name'             => lang('newsroom'),
    'folder'           => 'public',
    'title'            => lang('Newsroom'),
    'meta_description' => lang('Press releases and stories from the team: new models, updates, tests and how the phone is made.'),
    'tree'             => $page(array(
        $page_header(lang('Newsroom'), lang('Press releases and stories from the team: new models, updates, tests and how the phone is made.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A release: the one the list linked to (?r=), and its comments.
$standard_pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('press-release'),
    'folder'           => 'public',
    'title'            => lang('Press Release'),
    'meta_description' => lang('A press release from the Newsroom.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment in the Newsroom'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments'), 'post_comments'),
    )),
);

// The inbox: the customer's conversations (for the staff, everybody's),
// and the conversation page, where both sides answer as comments.
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
                            $para(lang('Repairs and trade-ins are followed on their own pages, from your account.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('My Support Requests'), '{{page:my_support}}', 'btn btn-sm btn-outline-primary rounded-pill'),
                                $link(lang('My Trade-ins'), '{{page:my_tradeins}}', 'btn btn-sm btn-outline-primary rounded-pill'),
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

// The help center: a search over the whole site - pages and the parts of
// pages marked as search sections (the features, the spec groups and the
// questions) - with the common questions under it, each a search section
// of its own.
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
    'meta_description' => lang('Search the site: the models and their specs, orders and delivery, trade-in, repairs and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the models and their specs, orders and delivery, trade-in and repairs, and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'h5 fw-semibold mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-cpu', lang('Tech Specs'), '{{page:specs}}'),
                                $help_topic('bi-layout-three-columns', lang('Compare Models'), '{{page:compare}}'),
                                $help_topic('bi-bag', lang('Buy Orbit'), '{{page:buy}}'),
                                $help_topic('bi-arrow-repeat', lang('Trade-in'), '{{page:tradein}}'),
                                $help_topic('bi-tools', lang('Repair and Support'), '{{page:support}}'),
                                $help_topic('bi-envelope', lang('Contact us'), '{{page:contact}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Something wrong with your phone? Open a support request; for anything else, write to us from the contact page.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Get Support'), '{{page:support}}', 'btn btn-sm btn-primary rounded-pill'),
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
                            $faq(1, lang('How do I move my data from my old phone?'), lang('Turn on your new Orbit and choose Copy from another phone: messages, photos and apps come across over Wi-Fi or a cable, from Android or iPhone.'), lang('transfer, move, copy, data, backup, setup'), 'lx-help-faq'),
                            $faq(2, lang('Which model should I choose?'), lang('The compare page puts the three side by side. Most people take the One; the Mini fits one hand, the One Plus has the biggest screen and the longest battery.'), lang('model, size, choose, difference, compare'), 'lx-help-faq'),
                            $faq(3, lang('How does trade-in work?'), lang('Tell us the model and condition of your old phone on the trade-in page and send two photos. A quote follows within two working days, and the credit comes off your new Orbit.'), lang('trade-in, old phone, credit, quote, exchange'), 'lx-help-faq'),
                            $faq(4, lang('My phone needs a repair. What do I do?'), lang('Open a support request with what happened and, for a repair under warranty, your receipt. You follow it from My Support Requests until it is solved.'), lang('repair, broken, screen, battery, warranty'), 'lx-help-faq'),
                            $faq(5, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'lx-help-faq'),
                        ), array('id' => 'lx-help-faq')),
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

// The launch list's welcome, sent to a new subscriber.
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the launch list')),
        $mail_para(lang('Thank you for joining. You hear first when a new model or colour is announced, what each update brings and when there is an offer for the people on the list.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('Read the Newsroom'), '{{page:blog}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$standard_widgets = array(

    // The launch list's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-bell', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('Join the launch list'), 'h4 fw-semibold mb-2'),
            $para(lang('Leave your e-mail address; we write when there is something new, a few times a year.'), 'text-body-secondary mb-4'),
            $newsletter_line('lx-newsletter-email'),
            $newsletter_consent('lx-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // The band at the foot of the pages: the same form, the launch list
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
                                    $icon('bi-bell', 'fs-1 lh-1 text-primary'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('Hear it first'), 'h4 fw-semibold mb-1'),
                                        $para(lang('New models, updates and offers for the launch list first. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '5'),
                            $col(array(
                                $newsletter_line('lx-band-email'),
                                $newsletter_consent('lx-band-consent'),
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
                $col(array($field('lx-contact-first-name', lang('First Name'), $input('lx-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('lx-contact-last-name', lang('Last Name'), $input('lx-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('lx-contact-email', lang('Email'), $input('lx-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('lx-contact-phone', lang('Phone'), $input('lx-contact-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('lx-contact-topic', lang('What is it about?'), $select('lx-contact-topic', 'topic', lang('Choose a topic…'), array(
                lang('A question about my order'),
                lang('Choosing a model'),
                lang('Delivery and returns'),
                lang('Press and media'),
                lang('Business and resellers'),
                lang('Something else'),
            ))),
            $field('lx-contact-message', lang('Your Message'), $textarea('lx-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The three newest releases on the home page.
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

    // The Newsroom: every release, newest first, with a search over them.
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
            'search_label'        => lang('Search the Newsroom...'),
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One release.
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
                            $link(lang('Back to the Newsroom'), '{{page:blog}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('Newsroom'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $para(lang('Press Release'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h1', lang('Meet Orbit: one phone in three sizes'), 'display-6 fw-semibold mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                // The author line leaves when the record has no contact name.
                                $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-2 ms-2', lang('Author'), array(
                                    $icon('bi-person'),
                                    $span(lang('Press Team'), 'pg-field-value', lang('Value'), $bind('submitter_name')),
                                )),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-phone'),
                                $span(lang('Ready for a new phone?'), '', lang('Text')),
                                $link(lang('Compare Models'), '{{page:compare}}', 'alert-link'),
                                $link(lang('Buy'), '{{page:buy}}', 'alert-link'),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // A release's comments: the release page's own Comments widget, under
    // the post view (whose record the comments belong to). Oldest first, an
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
                            $span(lang('A question about my order'), 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
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
                            $heading('h2', lang('Conversation not found'), 'h3 fw-semibold mt-3'),
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
                            $heading('h1', lang('A question about my order'), 'h3 fw-semibold mb-1', $bind('topic')),
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
                                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                                    array('text' => lang('Hello, I ordered an Orbit One last week. Can I still change the colour before it ships?')), $bind('message'))),
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
                        $para(lang('Battery'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
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
                $mail_line(lang('Subject'), array($span(lang('A question about my order'), '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, I ordered an Orbit One last week. Can I still change the colour before it ships?')), $bind('message'))),
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
                    array('text' => lang('A question about my order'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, I ordered an Orbit One last week. Can I still change the colour before it ships?')), $bind('message'))),
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
            ), array_merge($mail_style('border-top:1px solid #ede9fe;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
);

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
