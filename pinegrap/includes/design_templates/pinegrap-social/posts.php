<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", its posts: the page a member shares a
 * photo on (the Posts form, a collection, with the sample posts written in
 * when it is made), the explore grid with its topic filter, the posts of a
 * tag, the post's own page with its comments, the member's own posts, the
 * posts they saved, and the two lists the home page places. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A post is a record of the Posts form sent by a signed-in member: the
 * photo (an image field kept in the posts folder), its alt text, the
 * caption, a topic (one of the topic titles parts.php gives, so ?topic=
 * filters by the words a visitor sees), tags (words separated by commas,
 * which ?tag= looks for with "contains") and a place. Status and Featured
 * are office use only: a post is public from the moment it is sent, a
 * moderator takes it off the site by setting it Hidden, and the home page
 * shows the featured posts. Saving a post is following its comment thread;
 * Saved lists the posts the member follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$post_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The answers of the office fields. Every option is a stored answer (no
// empty prompt): the control panel prints only stored options.
$post_statuses = array(lang('Live'), lang('Hidden'));
$post_hidden   = $post_statuses[1];
$post_yes_no   = array(lang('Yes'), lang('No'));

// The topic titles, the values the Posts form stores.
$post_topic_titles = array();
foreach ($topics as $t) $post_topic_titles[] = $t['title'];

// A filter row of a list's or a record view's settings. Operators and
// dynamic values are the renderer's English constants, never translated.
$post_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// What keeps a post public: every list of posts and the post page carry it.
$post_live = array($post_filter('status', 'is not equal to', $post_hidden));

// A list of posts: the Posts form, the post page its rows open, the newest
// first, live posts only plus the filters given.
$post_list = function ($page_key, $ipp, $max, $more, $empty, $tree, $extra = array()) use ($post_live, $widget_root) {
    $config = array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:post_new}}',
        'detail_page_id'      => '{{tab:post}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'items_per_page'      => $ipp,
        'max_results'         => $max,
        'filters'             => array_merge($post_live, $more),
        'empty_message'       => $empty,
    ), $extra);
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};
// A member sees their own records: the viewer filter on the submitter or
// on the watcher, never every record for whoever may edit the form (editor
// off), so a moderator's own pages show their own too.
$post_own = function ($submitter, $watcher) {
    return array(
        'viewer_filter'           => true,
        'viewer_filter_submitter' => $submitter,
        'viewer_filter_watcher'   => $watcher,
        'viewer_filter_editor'    => false,
    );
};

// The author's name on a live post: the name on the account of the member
// who sent it (first and last name, else the nickname; never the profile's
// display name), opening the member's page. The samples are sent by
// the setup account, so they show none: the line leaves while it is empty.
$post_byline = function ($sample, $class) use ($el, $link, $page_link) {
    return $el('span', 'pg-hide-if-empty', lang('Author'), array(
        $link($sample, '#', $class, array(),
            array_merge($page_link('member', array('member' => 'submitter_name')), array('_bindings' => array('text' => 'submitter_name', 'href' => '__page_link')))),
    ));
};

// The comment count of a live post.
$post_count = function ($sample) use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1 small text-body-secondary', lang('Comment Count'), array(
        $icon('bi-chat'),
        $span((string)$sample, '', lang('Value'), $bind('number_of_comments')),
    ));
};

// A post as a square tile of a grid: the photo, the comment count over it,
// the whole tile opening the post's page.
$post_live_tile = function ($member_key = 'ayla', $n = 0) use ($el, $image, $link, $col, $attr, $social_posts, $photo, $post_count) {
    $p = $social_posts[$member_key][$n];
    return $col(array(
        $el('article', 'position-relative rounded-3 overflow-hidden psc-tile', lang('Post Tile'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('post-' . $member_key . '-' . ($n + 1), 600, 600), $p['alt'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'alt_text'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'position-absolute bottom-0 start-0 end-0 p-2 psc-tile-info', lang('Post Info'), array(
                $post_count($p['comments']),
            )),
            $link($p['caption'], '#', 'stretched-link visually-hidden-focusable', array(),
                array('_bindings' => array('text' => 'caption', 'href' => 'form_item_view'))),
        )),
    ), '4', '3', '', '6', 'psc-tile-col');
};
// The tiles of a list, in rows of four (two on a phone).
$post_live_grid = function ($member_key = 'ayla', $n = 0) use ($row, $loop, $post_live_tile) {
    return $row(array($loop(array($post_live_tile($member_key, $n)))), '3');
};

// A post as a card of the feed: who sent it and where, the photo, the
// caption, the topic, the tags and the comment count.
$post_live_card = function ($member_key = 'tom', $n = 0) use ($el, $para, $link, $image, $span, $icon, $bind, $bind_date, $attr, $members, $topics, $social_posts, $photo, $post_byline, $post_count) {
    $m = $members[$member_key];
    $p = $social_posts[$member_key][$n];
    return $el('article', 'card border-0 shadow-sm rounded-4 overflow-hidden mb-4 psc-post', lang('Member Post'), array(
        $el('div', 'card-header bg-body border-0 d-flex flex-wrap align-items-center justify-content-between gap-2 p-3', lang('Post Author'), array(
            $el('div', 'lh-sm', lang('Author'), array(
                $post_byline($m['name'], 'fw-semibold link-body-emphasis text-decoration-none'),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mb-0 pg-hide-if-empty', lang('Place'), array(
                    $icon('bi-geo-alt'), $span($p['place'], 'pg-field-value', lang('Value'), $bind('place')),
                )),
            )),
            $span(lang('2 days ago'), 'small text-body-secondary', lang('Date'), $bind_date('submitted_date_and_time', 'relative')),
        )),
        $el('a', 'd-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('post-' . $member_key . '-' . ($n + 1), 1080, 1080), $p['alt'], 'w-100 h-100 object-fit-cover',
                array('_bindings' => array('src' => 'photo', 'alt' => 'alt_text'), '_attrs' => array($attr('loading', 'lazy')))),
        ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
        $el('div', 'card-body p-3', lang('Card Body'), array(
            $para($p['caption'], 'mb-2', $bind('caption')),
            $el('p', 'd-flex flex-wrap align-items-center gap-2 small mb-2', lang('Topic and Tags'), array(
                $span($topics[$m['topic']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Topic'), $bind('topic')),
                $span(implode(', ', $p['tags']), 'pg-field-value text-body-secondary', lang('Tags'), $bind('tags')),
            )),
            $el('div', 'd-flex align-items-center justify-content-between gap-2', lang('Post Footer'), array(
                $post_count($p['comments']),
                $link(lang('Open the post'), '#', 'small fw-semibold link-primary text-decoration-none', array(),
                    array('_bindings' => array('href' => 'form_item_view'))),
            )),
        )),
    ));
};

// The topic links above the explore grid: All and one per topic, each
// opening the explore list with the topic set.
$post_topic_bar = function () use ($el, $link, $attr, $topics, $topic_url) {
    $children = array($link(lang('All Topics'), '{{page:explore}}', 'btn btn-sm btn-outline-secondary rounded-pill'));
    foreach (array_keys($topics) as $key) {
        $children[] = $link($topics[$key]['title'], $topic_url($key), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap gap-2 mb-4', lang('Topic Filter'), $children,
        array('_attrs' => array($attr('aria-label', lang('Filter the posts by topic')))));
};

// The tags of the samples, each once, as links to their pages: the tags a
// visitor may pick from on the tag page.
$post_tag_cloud = function () use ($social_posts, $tag_links) {
    $tags = array();
    foreach ($social_posts as $list) {
        foreach ($list as $p) {
            foreach ($p['tags'] as $tag) $tags[$tag] = true;
        }
    }
    return $tag_links(array_keys($tags));
};

// A page of a grid in the reading width: the page header, the parts given.
$post_grid_page = function ($title, $lead, $children, $more = array()) use ($page, $page_header, $el, $container) {
    return $page(array(
        $page_header($title, $lead, $more),
        $el('section', 'py-5', lang('Posts'), array($container($children))),
    ));
};

// A side card of the member's pages: a title, a line and a button.
$post_side_card = function ($icon_name, $title, $text, $link_text, $href) use ($el, $para, $link, $icon, $span) {
    return $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', $title, array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
            $icon($icon_name, 'text-primary'),
            $span($title, '', lang('Text')),
        )),
        $para($text, 'small text-body-secondary mb-3'),
        $link($link_text, $href, 'btn btn-sm btn-outline-primary rounded-pill px-3'),
    ));
};

// A page of the member's own: the header, the list in the main column, the
// side cards beside it.
$post_member_list_page = function ($title, $lead, $list_widget, $side) use ($member_page, $page_header, $el, $container, $row, $col, $widget) {
    return $member_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($list_widget)), '', '8'),
                    $col($side, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Share a photo. The form is the Posts collection; the sample posts are
// written in when the form is made, so the lists do not open empty
// (pg_cf_seed_sample_records()). What is sent opens the post's page.
// Addresses are ?r=<reference code>: the code never changes, a pretty
// address would follow every caption edit.
$sample_social_posts = array();
$post_featured_count = 0;
foreach ($social_posts as $member_key => $list) {
    foreach ($list as $n => $p) {
        $featured = $n === 0 && $post_featured_count < 4;
        if ($featured) $post_featured_count++;
        $sample_social_posts[] = array(
            'photo'    => $photo('post-' . $member_key . '-' . ($n + 1), 1080, 1080),
            'alt_text' => $p['alt'],
            'caption'  => $p['caption'],
            'topic'    => $topics[$members[$member_key]['topic']]['title'],
            'tags'     => implode(', ', $p['tags']),
            'place'    => $p['place'],
            'status'   => $post_statuses[0],
            'featured' => $featured ? $post_yes_no[0] : $post_yes_no[1],
        );
    }
}
$post_pages[] = array(
    'key'     => 'post_new',
    'name'    => lang('share-a-photo'),
    'folder'  => 'registration',
    'title'   => lang('Share a Photo'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Posts'),
        'collection'           => 1,
        'confirmation_message' => lang('Your photo is shared: it is live in the community now.'),
        'confirmation_page_id' => '{{tab:post}}',
        'pretty_urls'          => 0,
        'sample_records'       => $sample_social_posts,
        // The photo is the post's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $member_page(array(
        $page_header(lang('Share a Photo'), lang('Upload a photo, tell its story in a line and add a few tags. It is public as soon as you share it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('post_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Before you share'), array(
                            $heading('h2', lang('Before you share'), 'h5 fw-semibold mb-3'),
                            $check_list(array(
                                lang('Share a photo you took or may share, and name its maker when it is not you.'),
                                lang('Ask before you share a picture of someone.'),
                                lang('Describe the photo in a line for members who cannot see it.'),
                                lang('Pick the topic that fits and a few tags, so members find it.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                        $post_side_card('bi-shield-check', lang('Community Guidelines'),
                            lang('A moderator may hide a post that breaks the guidelines. Read them once: they are short.'),
                            lang('Read the guidelines'), '{{page:guidelines}}'),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Explore: every live post as a grid, narrowed by topic from the address,
// with a search over the captions, tags and places.
$post_pages[] = array(
    'key'              => 'explore',
    'name'             => lang('explore'),
    'folder'           => 'public',
    'title'            => lang('Explore'),
    'meta_description' => lang('The latest photos of the community: browse them by topic, or search for a place, a tag or a word.'),
    'tree'             => $post_grid_page(lang('Explore'), lang('The latest photos of the community. Pick a topic, or search for a place, a tag or a word.'),
        array($post_topic_bar(), $widget('explore_posts'))),
);

// The posts that carry a tag (?tag=<word>): the tags to pick from, then the
// live posts whose tags contain the word. The tags are one line of words,
// so the page cannot say which word it found and never takes it as its
// title; without a word it lists every live post. Every address lists
// posts the explore page already lists, so search engines and the site
// search leave it out.
$post_pages[] = array(
    'key'              => 'tag',
    'name'             => lang('tag'),
    'folder'           => 'public',
    'title'            => lang('Posts by tag'),
    'meta_description' => lang('The photos of the community that carry a tag.'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $post_grid_page(lang('Posts by tag'), lang('Pick a tag to see the photos that carry it, the newest first.'),
        array($widget('tag_posts')),
        array($el('nav', 'mt-4', lang('Tags'), array($post_tag_cloud()), array('_attrs' => array($attr('aria-label', lang('Tags'))))))),
);

// One post (?r=): the photo, who shared it, the caption, topic, tags and
// place, the way to report it; under it the comments, signed in and
// published at once, with the box that saves the post. The bare page shows
// "not found", so it stays out of the sitemap; its posts get there through
// the lists.
$post_pages[] = array(
    'key'              => 'post',
    'name'             => lang('post'),
    'folder'           => 'public',
    'title'            => lang('Post'),
    'meta_description' => lang('A photo shared by a member of the community, with its story and comments.'),
    'sitemap'          => false,
    'comments'         => array(
        'label'          => lang('Comment'),
        'allow_new'      => true,
        'auto_publish'   => true,
        'show_date'      => true,
        'login'          => true,
        'email_page'     => '{{tab:email_comment}}',
        'email_subject'  => lang('There is a new comment on a photo you saved'),
        'attachments'    => false,
        'select_name'    => false,
        'message'        => lang('Your comment is signed with the name in your profile and appears at once. Be kind: a moderator reads every report.'),
        'closed_message' => lang('This photo takes no new comments.'),
    ),
    'tree'             => $page(array(
        $widget('post_view'),
        $comments_section(lang('Comments'), 'post_comments'),
    ), false),
);

// My Posts: the live posts the member shared, the newest first.
$post_pages[] = array(
    'key'     => 'my_posts',
    'name'    => lang('my-posts'),
    'folder'  => 'registration',
    'title'   => lang('My Posts'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $post_member_list_page(lang('My Posts'), lang('The photos you shared, the newest first, with their comments.'), 'my_posts_list', array(
        $post_side_card('bi-plus-square', lang('Share a Photo'), lang('Upload a photo, write a caption and add a few tags.'), lang('Share a Photo'), '{{page:post_new}}'),
        $post_side_card('bi-eye-slash', lang('A post is missing?'),
            lang('A photo a moderator hid no longer shows anywhere, this page included. If you think that was a mistake, write to us.'),
            lang('Contact Us'), '{{page:contact}}'),
    )),
);

// Saved: the live posts the member saved, the ones whose comments they
// follow, the newest first.
$post_pages[] = array(
    'key'     => 'saved',
    'name'    => lang('saved-posts'),
    'folder'  => 'registration',
    'title'   => lang('Saved Posts'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $post_member_list_page(lang('Saved Posts'), lang('The photos you saved, the newest first. Their new comments come to you by e-mail.'), 'saved_posts_list', array(
        $post_side_card('bi-bookmark', lang('How saving works'),
            lang('Choose Save this photo under a post\'s comments, or tick the box when you comment: the post appears here and its new comments come to you by e-mail.'),
            lang('Explore'), '{{page:explore}}'),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$post_widgets = array();

// The Posts form: the photo and what is said about it in one group, then
// the moderators' fields, each in a box of its own with no hint. A new post
// stores Live and not featured.
$post_widgets['post_form'] = array(
    'page'   => 'post_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $form_step(1, lang('Your photo')),
        $field('sc-post-photo', lang('Photo'), pg_cf_with_role($file('sc-post-photo', 'photo', 'posts'), 'media'), 'mb-3',
            lang('A JPEG or PNG; a square or upright photo shows best in the grid.')),
        $field('sc-post-alt', lang('Describe the photo'), $input('sc-post-alt', 'text', 'alt_text', 'off', true, array(), array($attr('maxlength', '200'))), 'mb-3',
            lang('One line for members who cannot see it, such as "A grey dog asleep in the sun on grass".')),
        $form_step(2, lang('Its story')),
        $field('sc-post-caption', lang('Caption'), pg_cf_with_role($textarea('sc-post-caption', 'caption', 3), 'title'), 'mb-3',
            lang('A line or two: what is in the photo, or the story behind it.')),
        $row(array(
            $col(array($field('sc-post-topic', lang('Topic'), pg_cf_with_role($select('sc-post-topic', 'topic', lang('Choose a topic…'), $post_topic_titles), 'category'))), '6'),
            $col(array($field('sc-post-place', lang('Place (optional)'), $input('sc-post-place', 'text', 'place', 'off', false, array(), array($attr('maxlength', '80'))))), '6'),
        ), '3'),
        $field('sc-post-tags', lang('Tags (optional)'), $input('sc-post-tags', 'text', 'tags', 'off', false, array(), array($attr('maxlength', '120'))), 'mb-3',
            lang('A few single words, separated by commas, such as sourdough, baking.')),
        $agree('sc-post-rules', 'guidelines', lang('This photo is mine or I may share it, and it keeps to the community guidelines.')),
        // For the moderators only.
        $field('sc-post-status', lang('Status'), $select('sc-post-status', 'status', '', $post_statuses, false, $post_statuses[0], array('office_use_only' => true))),
        $field('sc-post-featured', lang('Featured on the home page'), $select('sc-post-featured', 'featured', '', $post_yes_no, false, $post_yes_no[1], array('office_use_only' => true))),
        $captcha(),
        $submit(lang('Share the photo'), 'btn btn-primary btn-lg rounded-pill px-4'),
        $loop(array()),
    )),
);

// Explore: every live post, twenty-four to a page, filtered by ?topic=,
// with a search over the captions, tags and places.
$post_widgets['explore_posts'] = $post_list('explore', 24, 0, array($post_filter('topic', 'is equal to', '', 'topic')),
    lang('No photo matches yet. Try another topic or other words, or share the first one.'),
    array($post_live_grid('ayla', 0)),
    array(
        'search_enabled' => true,
        'search_fields'  => array('caption', 'tags', 'place', 'topic', 'alt_text'),
        'search_width'   => 'full',
        'search_label'   => lang('Search the photos: a place, a tag, a word…'),
    ));

// The live posts whose tags contain ?tag=, twenty-four to a page. A line
// says so while no word is given and the list holds every post.
$post_widgets['tag_posts'] = $post_list('tag', 24, 0, array($post_filter('tags', 'contains', '', 'tag')),
    lang('No photo carries this tag yet. Pick another tag, or explore the community.'),
    array(
        $para(lang('No tag is chosen, so every photo is listed. Pick a tag above to narrow the list.'), 'small text-body-secondary mb-3', $show_when('no_filter_value')),
        $post_live_grid('mira', 0),
    ));

// The member's own live posts, the newest first, as feed cards.
$post_widgets['my_posts_list'] = $post_list('my_posts', 12, 0, array(),
    lang('You have not shared a photo yet. Share your first one: the community is waiting.'),
    array($loop(array($post_live_card('lena', 0)))), $post_own(true, false));

// The live posts the member saved: the ones whose comments they follow. A
// follower is kept per post page, the list's detail page, whose record view
// shows the Posts form (pg_sw_list_watcher_page_id()).
$post_widgets['saved_posts_list'] = $post_list('saved', 24, 0, array(),
    lang('You have saved no photo yet. Choose Save this photo under a post you love, and it appears here.'),
    array($post_live_grid('jonas', 0)), $post_own(false, true));

// The home page's featured posts, eight tiles at most.
$post_widgets['home_featured_posts'] = $post_list('home', 8, 8, array($post_filter('featured', 'is equal to', $post_yes_no[0])),
    lang('No featured photos yet.'),
    array($post_live_grid('kai', 0)));

// The home page's latest posts, six feed cards in two columns.
$post_widgets['home_latest_posts'] = $post_list('home', 6, 6, array(),
    lang('No photos yet. Share the first one.'),
    array($row(array($loop(array($col(array($post_live_card('sofia', 0)), '6')))), '4')));

// One post: the photo beside who shared it, the caption, topic, tags and
// place, the comment count, the report link and the moderators' Edit.
$post_sample_key = 'ayla';
$post_sample = $social_posts[$post_sample_key][0];
$post_sample_member = $members[$post_sample_key];
$post_widgets['post_view'] = array(
    'page'   => 'post',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:post_new}}',
        'access_control'      => 'public',
        'filters'             => $post_live,
        'title_field'         => 'caption',
        'not_found_message'   => lang('This photo is not available. It may have been removed or hidden by the moderators.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-image', 'display-5 text-body-tertiary'),
                $heading('h1', lang('Photo not available'), 'h3 fw-semibold mt-3'),
                $para(lang('This photo is not available. It may have been removed or hidden by the moderators.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('Explore'), '{{page:explore}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Member Post'), array(
                $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                    $icon('bi-check-circle', 'mt-1'),
                    $span(lang('Your photo is shared: it is live in the community now.'), '', lang('Text')),
                ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Explore'), '', lang('Link Text')),
                    ), array('href' => '{{page:explore}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'alt_text', 'w-100 h-100 object-fit-cover rounded-4', 'ratio ratio-1x1 bg-body-tertiary rounded-4',
                            $photo('post-' . $post_sample_key . '-1', 1080, 1080), $post_sample['alt']),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Post Author'), array(
                            $el('div', 'lh-sm', lang('Author'), array(
                                $post_byline($post_sample_member['name'], 'fw-semibold link-body-emphasis text-decoration-none'),
                                $span(lang('2 days ago'), 'd-block small text-body-secondary', lang('Date'), $bind_date('submitted_date_and_time', 'relative')),
                            )),
                        )),
                        $heading('h1', $post_sample['caption'], 'h4 fw-semibold mb-3', $bind('caption')),
                        $el('p', 'd-flex flex-wrap align-items-center gap-2 small mb-3', lang('Topic and Place'), array(
                            $span($topics[$post_sample_member['topic']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Topic'), $bind('topic')),
                            $el('span', 'd-inline-flex align-items-center gap-1 text-body-secondary pg-hide-if-empty', lang('Place'), array(
                                $icon('bi-geo-alt'), $span($post_sample['place'], 'pg-field-value', lang('Value'), $bind('place')),
                            )),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 small mb-3 pg-hide-if-empty', lang('Tags'), array(
                            $icon('bi-tags', 'text-body-secondary'),
                            $span(implode(', ', $post_sample['tags']), 'pg-field-value text-body-secondary', lang('Value'), $bind('tags')),
                        )),
                        $el('div', 'd-flex flex-wrap align-items-center gap-3 border-top pt-3', lang('Post Footer'), array(
                            $post_count($post_sample['comments']),
                            // Opens the report form with this post's address (?post=).
                            $el('a', 'd-inline-flex align-items-center gap-1 small link-secondary ms-auto', lang('Report'), array(
                                $icon('bi-flag'),
                                $span(lang('Report this photo'), '', lang('Link Text')),
                            ), array_merge(array('href' => '#', '_attrs' => array($attr('rel', 'nofollow'))), $page_link('report', array('post' => 'form_item_view')))),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'pt-4 pb-3'),
    )),
);

// The comments under a post: the post page's own Comments widget. Oldest
// first, an answer under the comment it answers, a featured comment first,
// twenty to a page; the follow box saves the post.
$post_widgets['post_comments'] = array(
    'page'   => 'post',
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
        'empty'     => lang('No comments on this photo yet. Say something kind.'),
        'heading'   => lang('Add a comment'),
        'label'     => lang('Your comment'),
        'sign_in'   => lang('Sign in to comment on this photo.'),
        'submit'    => lang('Post the comment on the photo'),
        'watch_box' => lang('Save this photo and e-mail me its new comments'),
        'follow'    => lang('Save this photo'),
        'unfollow'  => lang('Remove this photo from saved'),
        'close'     => lang('Stop comments on this photo'),
        'reopen'    => lang('Allow comments on this photo again'),
        'reply'     => true,
        'watch'     => true,
    )),
);

return array('pages' => $post_pages, 'widgets' => $post_widgets);
