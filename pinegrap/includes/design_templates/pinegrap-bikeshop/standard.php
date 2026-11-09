<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", the pages every template carries: contact
 * and the inbox (a message becomes a conversation), the newsletter, the
 * blog as "News" and its post page, the help center that searches the
 * whole site, the error page, the message and newsletter e-mails, the home
 * page's news, and the widgets that draw them. The privacy page and the
 * reply e-mail of the threads (email_reply, its widget email_comment) are
 * the requests part's. Included after parts.php, cards.php and mail.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
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

// The sample message of the canvas: the contact form's topic and words.
$sample_topic = lang('Workshop and repairs');
$sample_message = lang('Hello, my gears have started to skip on climbs. Can you look at the bike this week, or do I need to send a service request?');

// A line of the contact card: an icon and its words or link.
$contact_line = function ($icon_name, $child, $name, $class = 'mb-2') use ($el, $icon) {
    return $el('p', 'd-flex align-items-start gap-2 ' . $class, $name, array($icon($icon_name, 'text-primary mt-1'), $child));
};

// A box beside the main column: a steel surface with the orange edge, an
// icon and a title, one sentence and a square button.
$side_box = function ($name, $icon_name, $title, $text, $button, $href, $class = 'mb-4', $button_class = 'btn btn-sm btn-primary rounded-0 px-3')
    use ($el, $icon, $span, $para, $link) {
    return $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary ' . $class, $name, array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
            $icon($icon_name, 'text-primary'),
            $span($title, '', lang('Text')),
        )),
        $para($text, 'small text-body-secondary mb-3'),
        $link($button, $href, $button_class),
    ));
};

// Contact: a message to the shop; the staff are told, the sender gets a
// reply. A repair or a bike fit goes to its own request instead, which
// asks what the workshop or the fitter needs.
$standard_pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact Us'),
    'meta_description' => lang('A question about a bike, a part, the workshop, a bike fit or the group rides? Write to the shop.'),
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
        $page_header(lang('Contact Us'), lang('A question about a bike, a part or a ride: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-warning rounded-0 d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-wrench-adjustable', 'mt-1'),
                            $el('span', '', lang('Text'), array(
                                $span(lang('Bike needs a repair or a service? Send a service request, so the workshop knows the job and the day you can drop it off.') . ' ', '', lang('Text')),
                                $link(lang('Request a Service'), '{{page:service_request}}', 'alert-link'),
                            )),
                        )),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary', lang('Contact Details'), array(
                            $heading('h2', lang('The Shop'), 'pbs-display h5 text-uppercase mb-3'),
                            $contact_line('bi-geo-alt', $span($shop['address'], '', lang('Text')), lang('Address')),
                            $contact_line('bi-telephone', $link($shop['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $shop['phone']), 'link-body-emphasis text-decoration-none'), lang('Phone')),
                            $contact_line('bi-envelope', $link($shop['email'], 'mailto:' . $shop['email'], 'link-body-emphasis text-decoration-none text-break'), lang('Email')),
                            $contact_line('bi-clock', $span($shop['hours'], '', lang('Text')), lang('Opening Hours'), 'mb-3'),
                            $hours_list('small'),
                        )),
                        $side_box(lang('Inbox Note'), 'bi-inbox', lang('Follow the answer in your inbox'),
                            lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'),
                            lang('Open my inbox'), '{{page:inbox}}', 'mt-4 mb-0', 'btn btn-sm btn-outline-dark rounded-0 px-3'),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$standard_pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New bikes in the shop, workshop offers before the busy season and the next group rides, in one e-mail a month.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the bike shop\'s newsletter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('New bikes in the shop, workshop offers before the busy season and the next group rides, in one e-mail a month.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 border border-2 border-primary', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary', lang('What You Get'), array(
                            $heading('h2', lang('In every letter'), 'pbs-display h5 text-uppercase mb-3'),
                            $check_list(array(
                                lang('The new bikes, parts and accessories on the shop floor'),
                                lang('Workshop offers and when to book a service before the rush'),
                                lang('The group rides of the coming weeks and their routes'),
                                lang('One e-mail a month, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The blog, called News: every post, newest first, with a search.
$standard_pages[] = array(
    'key'              => 'blog',
    'name'             => lang('news'),
    'folder'           => 'public',
    'title'            => lang('News'),
    'meta_description' => lang('News from the bike shop: workshop tips, new bikes on the floor and stories from the group rides.'),
    'tree'             => $page(array(
        $page_header(lang('News'), lang('News from the bike shop: workshop tips, new bikes on the floor and stories from the group rides.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A post: the post the list linked to (?r=), and its comments.
$standard_pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A post from our blog.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment on the blog'),
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
                        $side_box(lang('Inbox Note'), 'bi-chat-left-text', lang('A question for our team?'),
                            lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'),
                            lang('New message'), '{{page:contact}}'),
                        $side_box(lang('Requests Note'), 'bi-wrench-adjustable', lang('Messages about a repair'),
                            lang('Messages about a service or a bike fit are written under the request itself.'),
                            lang('My Service Requests'), '{{page:my_services}}', 'mb-0', 'btn btn-sm btn-outline-dark rounded-0 px-3'),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
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
    )),
);

// The help center: a search over the whole site - pages and the parts of
// pages marked as search sections (the workshop board and prices, the
// frame sizes, the fit packages, the rides of the week, the shop's story)
// - with the common questions under it, each a search section of its own.
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
    'meta_description' => lang('Search the site: workshop prices, service requests, frame sizes, bike fit, group rides and common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the workshop and its prices, frame sizes, bike fit, the group rides, and the answers to the questions riders ask most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'pbs-display h5 text-uppercase mb-3'),
                            $el('nav', 'list-group list-group-flush', lang('Topics'), array(
                                $help_topic('bi-wrench-adjustable', lang('Workshop'), '{{page:workshop}}'),
                                $help_topic('bi-clipboard-check', lang('Request a Service'), '{{page:service_request}}'),
                                $help_topic('bi-rulers', lang('Frame Sizes'), '{{page:sizes}}'),
                                $help_topic('bi-person-arms-up', lang('Bike Fit'), '{{page:fit}}'),
                                $help_topic('bi-signpost-split', lang('Group Rides'), '{{page:rides}}'),
                                $help_topic('bi-envelope', lang('Contact Us'), '{{page:contact}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'pbs-display h5 text-uppercase mb-2'),
                            $para(lang('Write to us from the contact page; the answer comes to your inbox on this site.'), 'small text-body-secondary mb-3'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary rounded-0 px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_header(lang('Frequently asked questions'), lang('What riders ask the workshop and the shop most often.')),
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('Where do I see how my repair is going?'), lang('In My Service Requests, once you are signed in with the address you sent the request from. Its page shows the status the workshop sets, the quote and the mechanic\'s messages; write back there.'), lang('repair, service, status, quote, my service requests'), 'bikeshop-help-faq'),
                            $faq(2, lang('Do I have to book before I bring my bike in?'), lang('Send a service request with the day you would like to drop it off; the workshop answers with a day that has a free stand. A puncture is usually fixed while you wait.'), lang('book, drop-off, workshop, puncture, wait'), 'bikeshop-help-faq'),
                            $faq(3, lang('Which frame size do I need?'), lang('The Frame Sizes page gives each size by your height and inside leg. Between two sizes, come in for a test ride or a bike fit.'), lang('frame size, height, inseam, size chart'), 'bikeshop-help-faq'),
                            $faq(4, lang('Will I be left behind on a group ride?'), lang('Not on a no-drop ride: the group waits at the top of every climb. Each ride on the Group Rides page says its pace and whether it is a no-drop ride.'), lang('group ride, pace, no-drop, left behind'), 'bikeshop-help-faq'),
                            $faq(5, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'bikeshop-help-faq'),
                        ), array('id' => 'bikeshop-help-faq')),
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
    'tree'    => $page(array($widget('error_page'))),
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
        $mail_heading(lang('Welcome to the bike shop\'s newsletter')),
        $mail_para(lang('Thank you for subscribing. Once a month you hear which bikes are new in the shop, what the workshop offers before the busy season and where the next group rides go.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('Read our news'), '{{page:blog}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$standard_widgets = array(

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-paper', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('News from the workshop'), 'pbs-display h4 text-uppercase mb-2'),
            $para(lang('Leave your e-mail address; once a month we write about new bikes, workshop offers and the next group rides.'), 'text-body-secondary mb-4'),
            $newsletter_line('bikeshop-newsletter-email'),
            $newsletter_consent('bikeshop-newsletter-consent'),
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
                $col(array($field('bikeshop-contact-first-name', lang('First Name'), $input('bikeshop-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('bikeshop-contact-last-name', lang('Last Name'), $input('bikeshop-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('bikeshop-contact-email', lang('Email'), $input('bikeshop-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('bikeshop-contact-phone', lang('Phone'), $input('bikeshop-contact-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('bikeshop-contact-topic', lang('What is it about?'), $select('bikeshop-contact-topic', 'topic', lang('Choose a topic…'), array(
                lang('Bikes and parts'),
                $sample_topic,
                lang('Bike fit'),
                lang('Group rides'),
                lang('Something else'),
            ))),
            $field('bikeshop-contact-message', lang('Your Message'), $textarea('bikeshop-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The three newest posts on the home page.
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

    // News: every post, newest first, with a search over the posts.
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
            'search_label'        => lang('Search the news...'),
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One post, with the way to the workshop under it.
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
                            $icon('bi-journal-x', 'display-5 text-body-secondary'),
                            $heading('h2', lang('Post not found'), 'h3 fw-semibold mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the news'), '{{page:blog}}', 'btn btn-primary rounded-0 px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('All news'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $heading('h1', lang('Five checks before the first warm weekend'), 'pbs-display display-6 text-uppercase mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                // The author line leaves when the record has no contact name.
                                $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-2 ms-2', lang('Author'), array(
                                    $icon('bi-person'),
                                    $span($people[1]['name'], 'pg-field-value', lang('Value'), $bind('submitter_name')),
                                )),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-0 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'alert alert-secondary rounded-0 d-flex flex-wrap align-items-center gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-wrench-adjustable'),
                                $span(lang('Bike not ready for the road? The workshop checks it and tells you the price before it starts.'), '', lang('Text')),
                                $link(lang('Request a Service'), '{{page:service_request}}', 'alert-link'),
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
            $el('div', 'list-group rounded-0', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span($sample_topic, 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                            $span(lang('Date'), 'small text-body-secondary text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text'),
                            $span('0', 'pg-bike-num fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
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
                            $icon('bi-chat-left-dots', 'display-5 text-body-secondary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-semibold mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary rounded-0 px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array(
                            $el('div', 'alert alert-success rounded-0 d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info rounded-0', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                // The single-use set password link, for the person who sent the form
                                // while it is live; the sentence below covers a used or expired link.
                                $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-0', array(),
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
                            $heading('h1', $sample_topic, 'pbs-display h3 text-uppercase mb-1', $bind('topic')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', 'pg-bike-num', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array(
                                    $icon('bi-person-circle', 'fs-5 text-primary'),
                                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                )),
                                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                                    array('text' => $sample_message), $bind('message'))),
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
                    $el('input', 'form-control rounded-0', '', array(), array(
                        '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the site…')), $attr('aria-label', lang('Search the site'))),
                        '_bindings' => array('value' => 'search_query'),
                    )),
                    $el('button', 'btn btn-primary rounded-0 px-4', lang('Search Button'), array($icon('bi-search')), array(
                        '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                        '_bindings' => array('action' => 'submit_search'),
                    )),
                )),
            ), array('_bindings' => array('action' => 'search_form'))),
            $para(lang('Results heading'), 'fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
            $el('div', '', lang('Results'), array(
                $loop(array(
                    $el('article', 'py-3 border-bottom position-relative', lang('Result Row'), array(
                        $span(lang('Page'), 'badge rounded-0 bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                        $el('h2', 'h5 fw-semibold mb-1 position-static', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $para(lang('Workshop prices'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
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
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => $sample_message), $bind('message'))),
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
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => $sample_message), $bind('message'))),
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
            ), array_merge($mail_style('border-top:1px solid #cbd2d9;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
);

return array('pages' => $standard_pages, 'widgets' => $standard_widgets);
