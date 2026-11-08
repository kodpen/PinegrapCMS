<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", the pages every template carries:
 * contact and the inbox (a message becomes a conversation), the job alerts
 * (the newsletter), Career Advice (the blog) and its post page, the help
 * center that searches the whole site, the error page, the message and
 * job alert e-mails, the home page's guides and Google Reviews, and the
 * widgets that draw them. The privacy page and the reply e-mail of the
 * threads (email_reply, its widget email_comment) are the applications
 * part's. Included after parts.php, mail.php, jobs.php, applications.php
 * and employers.php in the same scope (it builds with the record pieces
 * of applications.php); returns array('pages' => ..., 'widgets' => ...).
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

// The topics of the contact form; a job or a staff request has its own
// form, so these are what is left.
$contact_topics = array(
    lang('A question about a job'),
    lang('My account or my CV'),
    lang('Working with the agency as an employer'),
    lang('Press'),
    lang('Something else'),
);
$msg_sample_topic = $contact_topics[1];
$msg_sample_text = lang('Hello, I have a new phone number and would like to add a newer CV to my account. How do I do that?');

// A line of the agency's details beside the contact form.
$contact_line = function ($icon_name, $child, $name, $class = 'mb-2') use ($el, $icon) {
    return $el('p', 'd-flex align-items-start gap-2 ' . $class, $name, array($icon($icon_name, 'text-primary mt-1'), $child));
};

// Contact: a message to the agency; the staff are told, the sender gets a
// reply. A job and a staff request each have their own form, which the
// note above the fields points to.
$standard_pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact Us'),
    'meta_description' => lang('A question about a job, your account or working with us? Write to the agency and a consultant answers.'),
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
        $page_header(lang('Contact Us'), lang('A question about a job, your account or working with us: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-primary d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $el('span', '', lang('Text'), array(
                                $span(lang('Looking for a job or for staff? Send your CV or a staff request, so a consultant has what they need.') . ' ', '', lang('Text')),
                                $link(lang('Send Your CV'), '{{page:apply}}', 'alert-link me-2'),
                                $link(lang('Hire Staff'), '{{page:hire}}', 'alert-link'),
                            )),
                        ), array('_attrs' => array($attr('role', 'note')))),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Our office'), 'prc-display h5 mb-3'),
                            $contact_line('bi-geo-alt', $span($agency['address'], '', lang('Text')), lang('Address')),
                            $contact_line('bi-telephone', $link($agency['phone'], 'tel:' . str_replace(' ', '', $agency['phone']), 'link-body-emphasis text-decoration-none'), lang('Phone')),
                            $contact_line('bi-envelope', $link($agency['email'], 'mailto:' . $agency['email'], 'link-body-emphasis text-decoration-none'), lang('Email')),
                            $contact_line('bi-clock', $span($agency['hours'], '', lang('Text')), lang('Office hours'), 'mb-0'),
                        )),
                        $el('div', 'p-4 rounded-4 border mt-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('Follow the answer in your inbox'), '', lang('Text')),
                            )),
                            $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Job alerts: the newsletter's page owns the form. What is sent adds the
// address to the newsletter's contact group, and the subscriber gets the
// welcome e-mail.
$standard_pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('job-alerts'),
    'folder'           => 'public',
    'title'            => lang('Job Alerts'),
    'meta_description' => lang('New jobs and career advice from our consultants, by e-mail every week. Free, and you can stop them at any time.'),
    'form'             => array(
        'form_name'            => lang('Job Alerts'),
        'confirmation_message' => lang('Thank you! You will now receive our job alerts.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to our job alerts'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Job Alerts'), lang('New jobs and career advice from our consultants, by e-mail every week. Free, and you can stop them at any time.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2 border-primary-subtle', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('In every alert'), 'prc-display h5 mb-3'),
                            $check_list(array(
                                lang('The jobs our consultants opened this week'),
                                lang('A career advice article: CVs, interviews, salaries'),
                                lang('Where the employers in your city are hiring'),
                                lang('One e-mail a week, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The blog, called Career Advice: every guide, newest first, with a search.
$standard_pages[] = array(
    'key'              => 'blog',
    'name'             => lang('career-advice'),
    'folder'           => 'public',
    'title'            => lang('Career Advice'),
    'meta_description' => lang('Guides from our consultants: writing a CV, preparing for an interview, asking for a salary and changing careers.'),
    'tree'             => $page(array(
        $page_header(lang('Career Advice'), lang('Guides from our consultants: writing a CV, preparing for an interview, asking for a salary and changing careers.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A guide: the post the list linked to (?r=), and its comments.
$standard_pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A career advice article from our consultants.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment on a career advice article'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments'), 'post_comments'),
    )),
);

// The inbox: the visitor's conversations (for the staff, everybody's), and
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
                                $span(lang('A question for the agency?'), '', lang('Text')),
                            )),
                            $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Requests Note'), array(
                            $para(lang('Messages about a job application or a staff request are written under the application or the request itself.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('My Applications'), '{{page:my_applications}}', 'btn btn-sm btn-outline-primary'),
                                $link(lang('My Staff Requests'), '{{page:my_enquiries}}', 'btn btn-sm btn-outline-primary'),
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
// pages marked as search sections (the sectors, the jobs, the steps, the
// services and the questions) - with the common questions under it, each
// a search section of its own.
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
    'meta_description' => lang('Search the site: jobs, sectors, applying, hiring through the agency, career advice and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the open jobs, our sectors, how applying and hiring work, and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'prc-display h5 mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-briefcase', lang('Open Jobs'), '{{page:jobs}}'),
                                $help_topic('bi-diagram-3', lang('Sectors'), '{{page:sectors}}'),
                                $help_topic('bi-file-earmark-person', lang('Send Your CV'), '{{page:apply}}'),
                                $help_topic('bi-folder2-open', lang('My Applications'), '{{page:my_applications}}'),
                                $help_topic('bi-people', lang('Hire Staff'), '{{page:hire}}'),
                                $help_topic('bi-journal-text', lang('Career Advice'), '{{page:blog}}'),
                                $help_topic('bi-envelope', lang('Contact Us'), '{{page:contact}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'prc-display h5 mb-2'),
                            $para(lang('Write to us from the contact page; the answer comes to your inbox on this site.'), 'small text-body-secondary mb-3'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
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
                            $faq(1, lang('Where do I see my application?'), lang('In My Applications, once you are signed in with the address you applied from. Its page shows the status and your consultant\'s messages.'), lang('application, status, my applications, CV'), 'rc-help-faq'),
                            $faq(2, lang('How do I send a newer CV?'), lang('Open your application\'s page and attach the new CV to a message there. Only you and our consultants can open it.'), lang('CV, update, new version, file, upload'), 'rc-help-faq'),
                            $faq(3, lang('Where do I follow my staff request?'), lang('In My Staff Requests, once you are signed in. Its page shows where the search stands, and the shortlist is sent to you as a message there.'), lang('staff request, employer, shortlist, status'), 'rc-help-faq'),
                            $faq(4, lang('How do I stop the job alerts?'), lang('Every job alert has a link to unsubscribe at the bottom; one click is enough.'), lang('job alerts, unsubscribe, newsletter, e-mail'), 'rc-help-faq'),
                            $faq(5, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'rc-help-faq'),
                        ), array('id' => 'rc-help-faq')),
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
// The job alerts' welcome, sent to a new subscriber.
$standard_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-job-alerts-welcome'),
    'title' => lang('Job Alerts Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to our job alerts')),
        $mail_para(lang('Thank you for subscribing. Every week you hear about the jobs our consultants opened and get one career advice article.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See the Open Jobs'), '{{page:jobs}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$post_missing = lang('This article could not be found. It may have been removed.');
$conversation_missing = lang('This conversation could not be found, or it is not yours to read.');

// The contact form's e-mails read the message the form just sent.
$message_widget = function ($page_key) use ($rc_detail_widget) {
    return $rc_detail_widget($page_key, 'contact', lang('The message could not be found.'), 'public');
};
// The message in a box: its topic and its words.
$mail_message = function ($name) use ($mail_box, $el, $bind, $attr, $msg_sample_topic, $msg_sample_text) {
    return $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
            array('text' => $msg_sample_topic, '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $msg_sample_text), $bind('message'))),
    ), $name);
};

$standard_widgets = array(

    // The job alerts' own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-bell', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('New jobs, every week'), 'prc-display h4 mb-2'),
            $para(lang('Leave your e-mail address; every week we write about the jobs our consultants opened and send one guide for your career.'), 'text-body-secondary mb-4'),
            $newsletter_line('rc-newsletter-email'),
            $newsletter_consent('rc-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // Contact: the fields are the page's form; the way to answer is the
    // sender's choice.
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $rc_contact_fields('rc-contact'),
            $field('rc-contact-topic', lang('What is it about?'), $select('rc-contact-topic', 'topic', lang('Choose a topic…'), $contact_topics)),
            $field('rc-contact-message', lang('Your Message'), $textarea('rc-contact-message', 'message', 6)),
            $radios('rc-contact-answer', 'answer_by', lang('How should we answer?'), $contact_choices, $contact_choices[0][0]),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The agency's rating and newest reviews on Google, under the
    // testimonials of the home page. The widget draws nothing until a
    // Places key and the agency's Place ID are saved, and this layout shows
    // only while Google returns reviews. Google asks for each review's
    // author and its link, and for "Google Maps" beside what is shown
    // without a map.
    'google_reviews' => array(
        'page'   => 'home',
        'slug'   => lang('google-reviews'),
        'config' => array('regionType' => 'google_reviews'),
        'tree'   => $widget_root(array(
            $el('div', 'mt-5 pt-4 border-top', lang('Google Reviews'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Rating'), array(
                    $heading('h3', lang('Reviews on Google'), 'prc-display h5 mb-0 me-2'),
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

    // The three newest guides on the home page.
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
            'empty_message'       => lang('No articles yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4'),
        )),
    ),

    // Career Advice: every guide, newest first, with a search over them.
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
            'search_label'        => lang('Search career advice...'),
            'empty_message'       => lang('No articles yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One guide, with the way to the open jobs under it.
    'blog_post' => array(
        'page'   => 'blog_post',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'access_control'      => 'public',
            'not_found_message'   => $post_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-journal-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Article not found'), 'h3 fw-semibold mt-3'),
                            $para($post_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to Career Advice'), '{{page:blog}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('All career advice'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $para(lang('Career Advice'), 'prc-eyebrow small text-uppercase fw-semibold text-primary mb-2'),
                            $heading('h1', lang('How to write a CV that gets you an interview'), 'prc-display display-6 mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                // The author line leaves when the record has no contact name.
                                $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-2 ms-2', lang('Author'), array(
                                    $icon('bi-person'),
                                    $span($consultants[0]['name'], 'pg-field-value', lang('Value'), $bind('submitter_name')),
                                )),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-info-circle'),
                                $span(lang('Ready for your next job? A consultant who knows your sector reads every CV.'), '', lang('Text')),
                                $link(lang('See the Open Jobs'), '{{page:jobs}}', 'alert-link'),
                            ), array('_attrs' => array($attr('role', 'note')))),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // A guide's comments: the post page's own Comments widget, under the
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

    // The inbox: conversations the visitor started (a signed-in submission
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
                    $el('a', 'list-group-item list-group-item-action p-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span($msg_sample_topic, 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
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
            'not_found_message'   => $conversation_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-semibold mt-3'),
                            $para($conversation_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array_merge(
                            $rc_sent_notes(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.')),
                            array(
                                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                        $icon('bi-arrow-left'),
                                        $span(lang('Inbox'), '', lang('Link Text')),
                                    ), array('href' => '{{page:inbox}}')),
                                    $edit_button(),
                                )),
                                $heading('h1', $msg_sample_topic, 'prc-display h3 mb-1', $bind('topic')),
                                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                    $icon('bi-calendar3'),
                                    $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                    $icon('bi-hash', 'ms-2'),
                                    $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                                )),
                                $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('First Message'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array_merge(
                                        array($icon('bi-person-circle', 'fs-5 text-primary')), $person_name())),
                                    $el('div', 'lh-lg', lang('Message'), array(), array_merge(array('text' => $msg_sample_text), $bind('message'))),
                                )),
                            )
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
                        $span(lang('Page'), 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                        $el('h2', 'h5 fw-semibold mb-1 position-static', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $para(lang('Sectors'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
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

    'email_message_admin' => $message_widget('email_new_message') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new message has arrived')),
            $mail_para(lang('It came from the contact form. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
            $mail_box(array(
                $mail_line(lang('Sender'), $person_name()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Answer by'), array($span($contact_choices[0][1], '', lang('Answer by'), $bind('answer_by')))),
            ), lang('Sender')),
            $mail_message(lang('Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_message_sender' => $message_widget('email_message_received') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('Your message has reached us')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
            $mail_message(lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
