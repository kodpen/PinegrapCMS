<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", its public pages: home, topics,
 * about, the community guidelines, the questions page, help, the privacy
 * notice and the terms of use. Included after parts.php in the same scope
 * (its builders and the community's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * The sample cards and tiles here come from parts.php (topics, members,
 * posts, guidelines, how joining works), so a change made there reaches
 * every page here. The home page places two list widgets of other parts:
 * home_featured_posts and home_latest_posts (the featured and the newest
 * member posts), home_news (Community News) and newsletter_band.
 * The community has no likes, no following and no messages between
 * members: the pages never offer them, and the questions page says how a
 * member talks to another (comments on a post).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// A picture that loads when it comes near the screen.
$pub_lazy = array('_attrs' => array($attr('loading', 'lazy')));

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$pub_band = function ($id, $eyebrow, $title, $text, $points, $picture, $alt, $flip = false, $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $image, $container, $row, $col, $check_list, $searchable, $pub_lazy) {
    $text_col = $col(array(
        $para($eyebrow, 'psc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'h2 fw-semibold mb-3'),
        $para($text, 'lead text-body-secondary mb-4'),
        $check_list($points),
    ), '', '6', '', '12', $flip ? 'order-lg-last' : '');
    $picture_col = $col(array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($picture, $alt, 'object-fit-cover', $pub_lazy),
        )),
    ), '', '6');
    return $el('section', $class, $title, array(
        $container(array(
            $row(array($text_col, $picture_col), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// Three tiles of an icon, a title and a line, in a row.
$pub_tiles = function ($items) use ($el, $heading, $para, $col, $row, $icon_tile) {
    $cols = array();
    foreach ($items as $v) {
        $cols[] = $col(array(
            $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', $v['title'], array(
                $icon_tile($v['icon'], 'mb-3'),
                $heading('h3', $v['title'], 'h5 fw-semibold mb-2'),
                $para($v['text'], 'small text-body-secondary mb-0'),
            )),
        ), '', '4');
    }
    return $row($cols, '4');
};

// Numbered steps as an ordered list.
$pub_step_list = function ($steps) use ($el, $step) {
    $items = array();
    foreach (array_values($steps) as $n => $s) $items[] = $step($n + 1, $s[0], $s[1]);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// A group of questions under its title; each question is a search section
// of its own ($faq).
$pub_faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

// A strip that sends the visitor on: a title, a line and one button.
$pub_strip = function ($title, $text, $button, $href, $class = 'py-5') use ($el, $heading, $para, $link, $container) {
    return $el('section', $class, $title, array(
        $container(array(
            $el('div', 'p-4 p-lg-5 rounded-4 bg-body-tertiary d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h4 fw-semibold mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link($button, $href, 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The note over a sample legal text: the site owner adapts it before it
// goes live.
$pub_sample_note = function ($text) use ($el, $icon, $span, $attr) {
    return $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
        $icon('bi-info-circle', 'mt-1'),
        $span($text, '', lang('Text')),
    ), array('_attrs' => array($attr('role', 'note'))));
};

// A titled part of a long text page (privacy, terms): a search section of
// its own, so a search for one of its words opens the page there.
$pub_text_part = function ($id, $title, $paragraphs, $topics = '') use ($el, $heading, $para, $searchable) {
    $children = array($heading('h2', $title, 'h4 fw-semibold mb-3'));
    $last = count($paragraphs) - 1;
    foreach (array_values($paragraphs) as $n => $text) $children[] = $para($text, $n === $last ? 'mb-0' : 'mb-3');
    return $el('section', 'mb-5', $title, $children, $searchable($id, $topics));
};

// The topic tiles, in the order of the menu.
$pub_topic_tiles = array();
foreach (array_keys($topics) as $key) $pub_topic_tiles[] = $topic_tile($key);

// The members who love each topic, in the order of the members.
$pub_topic_members = array();
foreach ($members as $member_key => $m) $pub_topic_members[$m['topic']][] = $member_key;

// ── Home ────────────────────────────────────────────────────────────────
// The hero's picture wall: the first post of four members.
$pub_wall = array();
foreach (array('ayla', 'tom', 'mira', 'sofia') as $member_key) {
    $p = $social_posts[$member_key][0];
    $pub_wall[] = $col(array(
        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('post-' . $member_key . '-1', 600, 600), $p['alt'], 'object-fit-cover'),
        )),
    ), '', '', '', '6');
}
$pub_people = array();
foreach (array('lena', 'emre', 'jonas', 'kai') as $member_key) $pub_people[] = $profile_card($member_key);

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A friendly photo community: share your pictures, tell the story behind them and meet people who love the same things.'),
    'tree'             => $page(array(
        // The community in one line, the search and the two ways in.
        $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('A photo community'), 'psc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Every picture has a story. Share yours.'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Post the photos you love, tell what happened just before you took them, and find people who see the world the way you do.'), 'lead text-body-secondary mb-4'),
                        $search_box('sc-hero-search', 'mb-4 psc-search'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Join the Community'), '{{page:register}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Look around first'), '{{page:explore}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $row($pub_wall, '3'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The topics, each opening the explore list filtered to it.
        $el('section', 'py-5', lang('Topics'), array(
            $container(array(
                $section_header(lang('Find your corner'), lang('Browse by topic'), lang('All topics'), '{{page:topics}}'),
                $row($pub_topic_tiles, '4'),
            )),
        )),

        // The posts the moderators feature, then the newest ones: the two
        // Posts lists of the posts part.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Member Posts'), array(
            $container(array(
                $section_header(lang('Picked by the moderators'), lang('Featured photos'), lang('Explore'), '{{page:explore}}'),
                $widget('home_featured_posts'),
            )),
        )),
        $el('section', 'py-5', lang('Newest Member Posts'), array(
            $container(array(
                $section_header(lang('Fresh from the members'), lang('Newest member posts'), lang('Explore'), '{{page:explore}}'),
                $widget('home_latest_posts'),
            )),
        )),

        // A few members to meet; the people page lists them all.
        $el('section', 'py-5 bg-body-tertiary', lang('People'), array(
            $container(array(
                $section_header(lang('Say hello'), lang('People to meet'), lang('All people'), '{{page:people}}'),
                $row($pub_people, '4'),
            )),
        )),

        // How joining works, in four steps beside a picture.
        $el('section', 'py-5', lang('How it works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('join', 1200, 900), lang('Friends looking at photos on a phone at a café table'), 'object-fit-cover', $pub_lazy),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('How it works'), 'psc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From sign-up to first post in minutes'), 'h2 fw-semibold mb-4'),
                        $pub_step_list($social_steps),
                        $link(lang('Read the Community Guidelines'), '{{page:guidelines}}', 'btn btn-outline-primary rounded-pill px-4 mt-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The newest Community News: the blog list of the standard part.
        $el('section', 'py-5 bg-body-tertiary', lang('Community News'), array(
            $container(array(
                $section_header(lang('From the team'), lang('Community News'), lang('All news'), '{{page:blog}}'),
                $widget('home_news'),
            )),
        )),

        // The newsletter band of the standard part.
        $widget('newsletter_band'),
    )),
);

// ── Topics ──────────────────────────────────────────────────────────────
// A section for each topic (#topic-<key>, the anchor the menu above opens):
// its picture and line, the tags its sample posts use, the posts of the
// members who love it and the link to the explore list filtered to it.
$pub_topic_links = array();
foreach ($topics as $key => $t) {
    $pub_topic_links[] = $el('li', 'nav-item', lang('Nav Item'), array($link($t['title'], '#topic-' . $key, 'nav-link px-0')));
}
$pub_topic_sections = array();
$pub_band_n = 0;
foreach ($topics as $key => $t) {
    $tiles = array();
    $tags = array();
    foreach (isset($pub_topic_members[$key]) ? $pub_topic_members[$key] : array() as $member_key) {
        foreach (array_keys($social_posts[$member_key]) as $n) {
            if (count($tiles) < 4) $tiles[] = $post_tile($member_key, $n);
            foreach ($social_posts[$member_key][$n]['tags'] as $tag) $tags[$tag] = true;
        }
    }
    $pub_topic_sections[] = $el('section', 'py-5' . ($pub_band_n % 2 === 1 ? ' bg-body-tertiary' : ''), $t['title'], array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary mb-3', lang('Picture Frame'), array(
                        $image($photo('topic-' . $key, 800, 600), $t['title'], 'object-fit-cover', $pub_lazy),
                    )),
                    $el('h2', 'h4 fw-semibold d-flex align-items-center gap-2 mb-2', lang('Title'), array(
                        $icon($t['icon'], 'text-primary'),
                        $span($t['title'], '', lang('Text')),
                    )),
                    $para($t['text'], 'text-body-secondary mb-3'),
                    $tag_links(array_slice(array_keys($tags), 0, 5)),
                    $link(lang('See the posts'), $topic_url($key), 'btn btn-outline-primary rounded-pill px-4 mt-3'),
                ), '', '5'),
                $col(array(
                    $row($tiles, '3'),
                ), '', '7'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable('topic-' . $key, $t['topics']));
    $pub_band_n++;
}

$public_pages[] = array(
    'key'              => 'topics',
    'name'             => lang('topics'),
    'folder'           => 'public',
    'title'            => lang('Topics'),
    'meta_description' => lang('Photography, food, travel, plants, pets and the outdoors: every topic of the community and the posts members share in it.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Topics'), lang('Pick what you love: each topic gathers the posts and the people around it.'), array(
            $el('ul', 'nav gap-3 small mt-3', lang('Topic Menu'), $pub_topic_links),
        ))),
        $pub_topic_sections
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$pub_about_values = array(
    array('icon' => 'bi-people', 'title' => lang('People before numbers'), 'text' => lang('No counters to chase: a post is here to be seen and talked about, not scored.')),
    array('icon' => 'bi-chat-quote', 'title' => lang('Pictures with a story'), 'text' => lang('A caption that says what happened turns a nice photo into one people remember.')),
    array('icon' => 'bi-shield-heart', 'title' => lang('Looked after by people'), 'text' => lang('Moderators read every report, and the guidelines are short enough to read in a minute.')),
);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Who we are: a small, friendly photo community where members share pictures, tell their stories and meet people who love the same things.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('A small, friendly place for photos and the stories behind them.')),
        $pub_band('our-story', lang('Our story'), lang('Why we started a photo community'),
            lang('We wanted a place where a picture of a loaf of bread or a sleeping dog gets a kind word and a question, not a number. So we built one, and asked our friends to come.'),
            array(
                lang('Every photo belongs to the member who shared it.'),
                lang('Comments are moderated with care, by people.'),
                lang('Topics and tags help you find what you love.'),
            ),
            $photo('about', 1200, 900), lang('A group of friends with cameras on a hillside at sunset'), false,
            lang('company, team, who we are, story')),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we stand for'), lang('How the community works'), lang('Community Guidelines'), '{{page:guidelines}}'),
                $pub_tiles($pub_about_values),
            )),
        ), $searchable('values', lang('values, kindness, moderation, safety'))),
        $pub_strip(lang('Press, partners and photo clubs'), lang('Write to us: the right person answers, usually within two working days.'), lang('Contact Us'), '{{page:contact}}'),
    )),
);

// ── Community guidelines ────────────────────────────────────────────────
$pub_report_steps = array(
    array(lang('You report the post'), lang('Open the post and use the report link under it; say in a line what is wrong.')),
    array(lang('A moderator reads it'), lang('Every report is read by a person, usually within a day.')),
    array(lang('The post is hidden if it breaks a rule'), lang('A hidden post leaves every list and its page; its member can ask us why.')),
    array(lang('You hear what happened'), lang('Your report shows its status under My Reports.')),
);

$public_pages[] = array(
    'key'              => 'guidelines',
    'name'             => lang('community-guidelines'),
    'folder'           => 'public',
    'title'            => lang('Community Guidelines'),
    'meta_description' => lang('The few rules that keep the community friendly: share what is yours, be kind, keep people safe, describe your pictures and report instead of arguing.'),
    'tree'             => $page(array(
        $page_header(lang('Community Guidelines'), lang('Five short rules, so everyone feels welcome to share.')),
        $el('section', 'py-5', lang('The Rules'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('The rules we all keep'), 'h4 fw-semibold mb-4'),
                        $rule_list(),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'p-4 rounded-4 bg-body-tertiary', lang('Good Posts'), array(
                            $heading('h2', lang('What makes a good post'), 'h5 fw-semibold mb-3'),
                            $check_list(array(
                                lang('A photo you are glad you took'),
                                lang('A caption that tells what happened'),
                                lang('A line of alt text describing the picture'),
                                lang('Two or three tags that say what is in it'),
                            )),
                        ), $searchable('good-posts', lang('tips, caption, alt text, tags, better photos'))),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rules', lang('rules, behaviour, harassment, copyright, privacy of others'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Reports'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('When something is wrong'), 'psc-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('What happens after a report'), 'h3 fw-semibold mb-4'),
                        $pub_step_list($pub_report_steps),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        ), $searchable('after-a-report', lang('report, moderation, hidden post, abuse, spam'))),
        $pub_strip(lang('A question about the rules?'), lang('Ask us before you post: we would rather answer than hide a picture.'), lang('Contact Us'), '{{page:contact}}'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about sharing photos, comments, saved posts, your profile, safety and reports in the community.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Sharing, comments, your profile and staying safe. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $pub_faq_group('sc-faq-posting', lang('Sharing photos'), array(
                            array(lang('How do I share a photo?'), lang('Sign in and choose Share a Photo at the top of any page: add the picture, a line describing it, a caption, a topic and a few tags.'), lang('upload, post, share, new post')),
                            array(lang('Which pictures may I post?'), lang('Photos you took or have the right to share. Say who made a picture when it was not you, and ask before you post someone\'s face.'), lang('copyright, allowed, rules, faces')),
                            array(lang('Can I change or remove my post?'), lang('Yes: open My Posts and edit the caption, topic or tags, or ask us from the contact page to take a post down.'), lang('edit post, delete post, remove')),
                            array(lang('Why should I write alt text?'), lang('It is the picture described in words, read aloud to members who cannot see it. One plain sentence is enough.'), lang('alt text, accessibility, screen reader')),
                        )),
                        $pub_faq_group('sc-faq-talking', lang('Comments and saved posts'), array(
                            array(lang('How do I talk to another member?'), lang('Comment on their post: the conversation stays under the picture, where everyone can join in. There are no private messages between members.'), lang('message, chat, contact a member, private message')),
                            array(lang('Can I follow a member?'), lang('There is no following here. Open their profile to see every post they shared, or save a post to come back to it.'), lang('follow, followers, subscribe')),
                            array(lang('How do I save a post?'), lang('Tick the save box under the comments of a post: it appears in Saved Posts, and you hear by e-mail when someone comments.'), lang('save, bookmark, favourites, notify')),
                        )),
                        $pub_faq_group('sc-faq-profile', lang('Your profile and account'), array(
                            array(lang('What does my profile show?'), lang('The name, picture, city, favourite topic and the few words you choose, and the posts you shared. Your e-mail address is never shown.'), lang('profile, public, visible, e-mail')),
                            array(lang('How do I change my profile?'), lang('Open My Profile from the member menu and edit it; the change shows on your public profile at once.'), lang('edit profile, avatar, bio, picture')),
                            array(lang('How do I close my account?'), lang('Write to us from the contact page with the e-mail address you signed up with; we remove your account and your posts.'), lang('delete account, close account, leave')),
                        )),
                        $pub_faq_group('sc-faq-safety', lang('Safety and reports'), array(
                            array(lang('Someone posted a picture of me. What can I do?'), lang('Report the post and say it shows you: a moderator hides it while we look at it.'), lang('my photo, privacy, picture of me')),
                            array(lang('How do I report a post?'), lang('Open the post and use the report link under it. You can follow your report under My Reports.'), lang('report, abuse, spam, offensive')),
                            array(lang('Who reads the reports?'), lang('The community\'s moderators: people, not a program. They read every report and hide a post that breaks the guidelines.'), lang('moderators, staff, who decides')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $pub_strip(lang('Still have a question?'), lang('The help page walks through the community step by step, and we answer every message.'), lang('Go to Help'), '{{page:help}}', 'pb-5'),
    )),
);

// ── Help ────────────────────────────────────────────────────────────────
// The help topics: each tile opens the page that does the job.
$pub_help_topics = array(
    array('icon' => 'bi-person-plus', 'title' => lang('Getting started'), 'text' => lang('Create your account and set up your profile.'), 'href' => '{{page:register}}', 'link' => lang('Create an account')),
    array('icon' => 'bi-plus-square', 'title' => lang('Sharing a photo'), 'text' => lang('Picture, alt text, caption, topic and tags.'), 'href' => '{{page:post_new}}', 'link' => lang('Share a Photo')),
    array('icon' => 'bi-person-badge', 'title' => lang('Your profile'), 'text' => lang('Your name, picture and the topic you love most.'), 'href' => '{{page:my_profile}}', 'link' => lang('My Profile')),
    array('icon' => 'bi-bookmark', 'title' => lang('Comments and saved posts'), 'text' => lang('Talk under a picture and keep the ones you love.'), 'href' => '{{page:saved}}', 'link' => lang('Saved Posts')),
    array('icon' => 'bi-flag', 'title' => lang('Reports'), 'text' => lang('Tell the moderators about a post that breaks a rule.'), 'href' => '{{page:my_reports}}', 'link' => lang('My Reports')),
    array('icon' => 'bi-gear', 'title' => lang('Your account'), 'text' => lang('Password, e-mail address and the e-mails you receive.'), 'href' => '{{page:my_account}}', 'link' => lang('My Account')),
);
$pub_help_cols = array();
foreach ($pub_help_topics as $h) {
    $pub_help_cols[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 position-relative psc-lift', $h['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($h['icon'], 'mb-3'),
                $heading('h3', $h['title'], 'h5 fw-semibold mb-2'),
                $para($h['text'], 'small text-body-secondary mb-3'),
                $link($h['link'], $h['href'], 'stretched-link fw-semibold link-primary text-decoration-none'),
            )),
        )),
    ), '6', '4');
}

$public_pages[] = array(
    'key'              => 'help',
    'name'             => lang('help'),
    'folder'           => 'public',
    'title'            => lang('Help'),
    'meta_description' => lang('Help with the community: getting started, sharing a photo, your profile, comments, saved posts, reports and your account.'),
    'tree'             => $page(array(
        $page_header(lang('Help'), lang('Everything you need to share, comment and find your people.'), array(
            $search_box('sc-help-search', 'mt-4 psc-search'),
        )),
        $el('section', 'py-5', lang('Help Topics'), array(
            $container(array(
                $row($pub_help_cols, '4'),
            )),
        )),
        $pub_band('getting-started', lang('Getting started'), lang('Your first day in the community'),
            lang('Four steps, and the first one takes a minute.'),
            array(
                lang('Sign up with your e-mail address'),
                lang('Add a picture and a few words to your profile'),
                lang('Share a photo and tag what is in it'),
                lang('Comment on a post you like'),
            ),
            $photo('help', 1200, 900), lang('A person taking a photo of a cup of coffee with a phone'), false,
            lang('start, sign up, register, new member, first post'), 'py-5 bg-body-tertiary'),
        $pub_band('alt-text', lang('Describe your pictures'), lang('Writing good alt text'),
            lang('Alt text is read aloud to members who cannot see the photo. Say what matters in one plain sentence.'),
            array(
                lang('Say what is in the picture, not that it is a picture'),
                lang('Name the main thing first, then where it is'),
                lang('Leave out what the caption already says'),
            ),
            $photo('alt-text', 1200, 900), lang('A dog sitting on a wooden pier by a lake'), true,
            lang('alt text, accessibility, describe image, blind, screen reader')),
        $pub_strip(lang('Need a hand?'), lang('Read the questions members ask most, or write to us: a person answers.'), lang('Frequently Asked Questions'), '{{page:faq}}'),
    )),
);

// ── Privacy ─────────────────────────────────────────────────────────────
// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$public_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the community keeps about you, what other members see, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the community keeps about you, what other members see, and what you can ask of us.')),
        $el('section', 'py-5', lang('Policy'), array(
            $container(array($row(array($col(array(
                $pub_sample_note(lang('Sample text: adapt it to your community and to the data protection law that applies to you before you publish.')),
                $pub_text_part('what-we-keep', lang('What we keep'), array(
                    lang('When you open an account, share a post, write a comment, send a report or sign up for the newsletter, we keep your name, your e-mail address and what you send us.'),
                    lang('We use it to run your account, show your posts, send the e-mails you asked for and look at reports. We never sell it and never give it to advertisers.'),
                ), lang('personal data, KVKK, GDPR, data we collect')),
                $pub_text_part('what-others-see', lang('What other members see'), array(
                    lang('Your profile, your posts and your comments are public: anyone visiting the site can see them. Your e-mail address and your reports are never shown.'),
                    lang('A photo can carry the place it was taken in its file. Check what your phone adds before you share a picture of your home.'),
                ), lang('public, visible, profile, location, metadata')),
                $pub_text_part('your-choices', lang('Your choices'), array(
                    lang('You can change your profile and your posts at any time, unsubscribe from any e-mail with the link it carries, and ask us to see, correct or delete what we keep about you through the contact page.'),
                ), lang('delete my data, unsubscribe, correct, access')),
            ), '', '8')), '4', 'center'))),
        )),
    ), false),
);

// ── Terms ───────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'terms',
    'name'             => lang('terms-of-use'),
    'folder'           => 'public',
    'title'            => lang('Terms of Use'),
    'meta_description' => lang('The terms of using the community: your account, the photos you share, the guidelines and moderation.'),
    'tree'             => $page(array(
        $page_header(lang('Terms of Use'), lang('The agreement between you and the community, in plain words.')),
        $el('section', 'py-5', lang('Terms'), array(
            $container(array($row(array($col(array(
                $pub_sample_note(lang('Sample text: adapt it to your community and have it checked by a lawyer before you publish.')),
                $pub_text_part('terms-account', lang('Your account'), array(
                    lang('You need an account to post and comment. Keep your password to yourself: what is done with your account is done in your name.'),
                ), lang('account, password, age, sign up')),
                $pub_text_part('terms-content', lang('Your photos'), array(
                    lang('Your photos stay yours. By sharing one, you let the community show it on the site, in its lists and in its e-mails for as long as it is posted.'),
                    lang('Share only what you took or have the right to share; you answer for what you post.'),
                ), lang('copyright, licence, ownership, photos')),
                $pub_text_part('terms-moderation', lang('The guidelines and moderation'), array(
                    lang('Everyone keeps the Community Guidelines. Moderators may hide a post or a comment that breaks them and close an account that keeps breaking them.'),
                ), lang('moderation, rules, ban, hidden')),
                $pub_text_part('terms-changes', lang('Changes to these terms'), array(
                    lang('If these terms change, we say so on this page and in Community News before the change applies.'),
                ), lang('changes, updates, notice')),
            ), '', '8')), '4', 'center'))),
        )),
    ), false),
);

// The home page's lists (home_featured_posts, home_latest_posts,
// home_news) and the newsletter band belong to the parts that
// build those lists; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
