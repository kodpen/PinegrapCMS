<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", its profiles: the member's own
 * profile page (the Profiles form, a collection, with the sample members
 * written in when it is made), the people list with its topic filter, a
 * member's public page with their profile and their posts, and the staff
 * list of every profile. Included after parts.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A profile is a record of the Profiles form, sent by a signed-in member.
 * A record view lets only the staff edit a record, so the member changes
 * their profile by sending the form again: every list of a member's
 * profile shows their newest one. The member's public page reads ?member=,
 * the author name of the member's records (submitter_name, the contact's
 * name, never the login username), and lists the profile and the posts
 * sent under it. The interests are a pick list whose answers are the
 * topics' titles, so ?topic= filters the people by the words a visitor
 * sees.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$profile_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$profile_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The member the address names (?member=): the author name of their
// records. Without it the filter is left out, so the member page shows its
// content only while has_filter_value holds.
$profile_member_param = $profile_filter('submitter_name', 'is equal to', '', 'member');

// The topic titles, the answers of the interests list.
$profile_topic_titles = array();
foreach ($topics as $t) $profile_topic_titles[] = $t['title'];

// A list's settings over the Profiles form, newest first: a member who
// sends the form again is shown with the new profile.
$profile_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:my_profile}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    ), $more);
};

// The member's picture from the record, in a round frame that leaves when
// the profile has none. The name beside it says who it is (empty alt).
$profile_avatar = function ($size, $sample_key) use ($el, $image, $photo) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-inline-block', lang('Picture Frame'), array(
            $image($photo('avatar-' . $sample_key, 160, 160), '', 'rounded-circle object-fit-cover flex-shrink-0 ' . $size,
                array('_bindings' => array('src' => 'avatar'))),
        )),
    ));
};

// The member's city and interest, each leaving when the profile has none.
$profile_facts = function ($m, $class) use ($el, $span, $icon, $bind, $topics) {
    return $el('p', $class, lang('Place and Topic'), array(
        $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1', lang('City'), array(
            $icon('bi-geo-alt'),
            $span($m['city'], 'pg-field-value', lang('Value'), $bind('city')),
        )),
        $el('span', 'pg-hide-if-empty', lang('Topic'), array(
            $span($topics[$m['topic']]['title'], 'pg-field-value badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Value'), $bind('interests')),
        )),
    ));
};

// A row of the people list: picture, display name, city, interest and the
// words of the profile; the name opens the member's page (?member= with
// the author name of the record).
$profile_list_card = function ($sample_key) use ($el, $para, $link, $col, $bind, $members, $page_link, $profile_avatar, $profile_facts) {
    $m = $members[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 position-relative psc-lift', lang('Profile Card'), array(
            $el('div', 'card-body p-4 text-center', lang('Card Body'), array(
                $el('div', 'mb-3', lang('Picture'), array($profile_avatar('psc-avatar-lg', $sample_key))),
                $el('h3', 'h6 fw-bold mb-1 position-static', lang('Title'), array(
                    $link($m['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array_merge($page_link('member', array('member' => 'submitter_name')), array('_bindings' => array('text' => 'display_name', 'href' => '__page_link')))),
                )),
                $profile_facts($m, 'd-flex flex-wrap justify-content-center align-items-center gap-2 small text-body-secondary mb-2'),
                $para($m['bio'], 'small mb-0', $bind('bio')),
            )),
        )),
    ), '6', '3');
};

// The people cards of a list, in rows of four from lg up.
$profile_grid = function ($sample_key) use ($widget_root, $row, $loop, $profile_list_card) {
    return $widget_root(array($row(array($loop(array($profile_list_card($sample_key)))), '4')));
};

// The profile as its own page shows it: the picture, the display name, the
// city and interest, the words and the member's own address. The address
// is printed, never made a link: a member's text is not checked as an
// address before a link would carry it.
$profile_header = function ($sample_key, $title_tag) use ($el, $heading, $para, $span, $icon, $bind, $members, $profile_avatar, $profile_facts) {
    $m = $members[$sample_key];
    return $el('div', 'd-flex flex-column flex-md-row align-items-center align-items-md-start gap-4 text-center text-md-start', lang('Profile'), array(
        $profile_avatar('psc-avatar-lg', $sample_key),
        $el('div', 'flex-grow-1', lang('Text'), array(
            $heading($title_tag, $m['name'], 'h2 fw-bold mb-1', $bind('display_name')),
            $profile_facts($m, 'd-flex flex-wrap justify-content-center justify-content-md-start align-items-center gap-2 text-body-secondary mb-3'),
            $el('div', 'pg-hide-if-empty mb-2', lang('About'), array(
                $el('p', 'pg-field-value mb-0', lang('Value'), array(), array_merge(array('text' => $m['bio']), $bind('bio'))),
            )),
            $el('p', 'pg-hide-if-empty d-flex justify-content-center justify-content-md-start align-items-center gap-2 small mb-0', lang('Website'), array(
                $icon('bi-link-45deg'),
                $span('example.com', 'pg-field-value text-break', lang('Value'), $bind('website')),
            )),
        )),
    ));
};

// The topic links above the people list: one per topic, each opening the
// list filtered to the members who love it.
$profile_topic_bar = function () use ($el, $link, $span, $attr, $topics) {
    $children = array($span(lang('Members who love'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('Everyone'), '{{page:people}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($topics as $t) {
        $children[] = $link($t['title'], '{{page:people}}?topic=' . rawurlencode($t['title']), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children,
        array('_attrs' => array($attr('aria-label', lang('Filter the people by topic')))));
};

// A post of the member's page as a square tile: the photo from the record,
// opening the post's page. The Posts form's fields: photo, alt_text,
// caption, status.
$profile_post_tile = function ($sample_key) use ($el, $image, $link, $col, $social_posts, $photo) {
    $p = $social_posts[$sample_key][0];
    return $col(array(
        $el('article', 'position-relative rounded-3 overflow-hidden psc-tile', lang('Post Tile'), array(
            $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('post-' . $sample_key . '-1', 600, 600), $p['alt'], 'object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'alt_text'))),
            )),
            $link($p['caption'], '#', 'stretched-link visually-hidden-focusable', array(),
                array('_bindings' => array('text' => 'caption', 'href' => 'form_item_view'))),
        )),
    ), '4', '3', '', '6', 'psc-tile-col');
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every member with a profile, narrowed by topic from the address, with a
// search over the profiles' words.
$profile_pages[] = array(
    'key'              => 'people',
    'name'             => lang('people'),
    'folder'           => 'public',
    'title'            => lang('People'),
    'meta_description' => lang('Meet the members of the community: who they are, where they live and what they love to photograph.'),
    'tree'             => $page(array(
        $page_header(lang('People'), lang('Find members who love what you love. Choose a topic or search for a name or a city.')),
        $el('section', 'py-5', lang('People'), array(
            $container(array($profile_topic_bar(), $widget('people_list'))),
        )),
    )),
);

// A member's public page: their profile and their live posts, newest first.
// Without a name in the address the page says how to get to one.
$profile_pages[] = array(
    'key'              => 'member',
    'name'             => lang('member'),
    'folder'           => 'public',
    'title'            => lang('Member Profile'),
    'meta_description' => lang('A member of the community: their profile and the photos they shared.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $el('section', 'py-5 border-bottom bg-body-tertiary', lang('Profile'), array(
            $container(array($widget('member_profile'))),
        )),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array(
                $section_header(lang('Their photos'), lang('Posts by this member')),
                $widget('member_posts'),
            )),
        )),
    )),
);

// The member's own profile: how others see it, and the form that sets it
// up. The form is the Profiles collection; the sample members are written
// in when the form is made, so the people list does not open empty
// (pg_cf_seed_sample_records()).
$sample_profiles = array();
foreach ($members as $key => $m) {
    $sample_profiles[] = array(
        'display_name' => $m['name'],
        'bio'          => $m['bio'],
        'city'         => $m['city'],
        'interests'    => $topics[$m['topic']]['title'],
        'avatar'       => $photo('avatar-' . $key, 160, 160),
        'website'      => '',
    );
}
$profile_pages[] = array(
    'key'     => 'my_profile',
    'name'    => lang('my-profile'),
    'folder'  => 'registration',
    'title'   => lang('My Profile'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Profiles'),
        'collection'           => 1,
        'confirmation_message' => lang('Your profile is saved. Members see it on your page and in the people list.'),
        'confirmation_page_id' => '{{tab:my_profile}}',
        'sample_records'       => $sample_profiles,
        // The picture is the profile's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'avatar',
    ),
    'tree'    => $member_page(array(
        $page_header(lang('My Profile'), lang('Your picture, your name and a few words about you: what other members see on your page.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Your Profile Card'), array($widget('my_profile_card'))),
                    ), '', '5'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array(
                            $heading('h2', lang('Set up your profile'), 'h5 fw-bold mb-1'),
                            $para(lang('Send the form again whenever you want to change something: your newest profile is the one members see.'), 'small text-body-secondary mb-4'),
                            $widget('profile_form'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The staff list of every profile, newest first, with a search; the
// list's own pencil on each row opens the record to edit it.
$profile_pages[] = array(
    'key'     => 'staff_members',
    'name'    => lang('staff-members'),
    'folder'  => 'private',
    'title'   => lang('All Profiles'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('All Profiles'), lang('Every profile the members sent, newest first. Edit one to correct a name or remove a picture that breaks the guidelines.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_members_list'))),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$profile_widgets = array(

    // The member's Profiles form: display name, picture, city, the topic
    // they love, a few words and their own address.
    'profile_form' => array(
        'page'   => 'my_profile',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('sc-profile-name', lang('Name shown to members'), pg_cf_with_role($input('sc-profile-name', 'text', 'display_name', 'nickname'), 'title'), 'mb-3',
                lang('The name members see on your page and in the people list. Your posts show the name in your account details.')),
            $field('sc-profile-avatar', lang('Profile picture'), pg_cf_with_role($file('sc-profile-avatar', 'avatar', 'avatars'), 'media'), 'mb-3',
                lang('A square picture looks best; it is shown in a circle.')),
            $row(array(
                $col(array($field('sc-profile-city', lang('City'), $input('sc-profile-city', 'text', 'city', 'address-level2', false))), '6'),
                $col(array($field('sc-profile-interests', lang('The topic you love most'),
                    pg_cf_with_role($select('sc-profile-interests', 'interests', lang('Choose a topic…'), $profile_topic_titles), 'category'))), '6'),
            ), '3'),
            $field('sc-profile-bio', lang('About you'), pg_cf_with_role($textarea('sc-profile-bio', 'bio', 3), 'description'), 'mb-3',
                lang('Two or three lines: what you photograph and why.')),
            $field('sc-profile-website', lang('Your website'), $input('sc-profile-website', 'url', 'website', 'url', false), 'mb-4',
                lang('Shown as text on your page, for example example.com.')),
            $submit(lang('Save my profile')),
            $loop(array()),
        )),
    ),

    // How others see the member: their newest profile, or how to begin.
    'my_profile_card' => array(
        'page'   => 'my_profile',
        'slug'   => lang('form-list'),
        'config' => $profile_list_config(array(
            'items_per_page'          => 1,
            'max_results'             => 1,
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => false,
            'viewer_filter_editor'    => false,
            'empty_message'           => lang('You have no profile yet. Fill in the form to introduce yourself to the community.'),
        )),
        'tree'   => $widget_root(array(
            $para(lang('How members see you'), 'psc-eyebrow text-uppercase fw-semibold text-primary mb-3'),
            $el('div', '', lang('Rows'), array($loop(array(
                $profile_header('ayla', 'h2'),
                $el('div', 'mt-4', lang('Link'), array(
                    $link(lang('Open my public page'), '#', 'btn btn-sm btn-outline-primary rounded-pill', array(),
                        $page_link('member', array('member' => 'submitter_name'))),
                )),
            )))),
        )),
    ),

    // Every member, filtered by the topic in the address, with a search.
    'people_list' => array(
        'page'   => 'people',
        'slug'   => lang('form-list'),
        'config' => $profile_list_config(array(
            'items_per_page' => 24,
            'filters'        => array($profile_filter('interests', 'is equal to', '', 'topic')),
            'search_enabled' => true,
            'search_fields'  => array('display_name', 'city', 'interests', 'bio'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the people: a name, a city, a topic...'),
            'empty_message'  => lang('No member matches yet. Try another topic, or be the first: set up your profile.'),
        )),
        'tree'   => $profile_grid('tom'),
    ),

    // The member the address names: their newest profile. Without a name,
    // how to find one.
    'member_profile' => array(
        'page'   => 'member',
        'slug'   => lang('form-list'),
        'config' => $profile_list_config(array(
            'items_per_page' => 1,
            'max_results'    => 1,
            'filters'        => array($profile_member_param),
            'empty_message'  => lang('This member has not set up a profile yet.'),
        )),
        'tree'   => $widget_root(array(
            $el('div', '', lang('Rows'), array($loop(array($profile_header('ayla', 'h1')))), $show_when('has_filter_value')),
            $el('div', 'alert alert-secondary d-flex gap-2 align-items-start mb-0', lang('No Member Chosen'), array(
                $icon('bi-person', 'mt-1'),
                $el('p', 'mb-0', lang('Text'), array(
                    $span(lang('Open a member\'s name on a post or in the people list to see their profile.'), 'me-1', lang('Text')),
                    $link(lang('See the people'), '{{page:people}}', 'alert-link'),
                )),
            ), $show_when('no_filter_value')),
        )),
    ),

    // The live posts sent under the name in the address, newest first.
    'member_posts' => array(
        'page'   => 'member',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:post_new}}',
            'detail_page_id'      => '{{tab:post}}',
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'items_per_page'      => 24,
            'filters'             => array($profile_member_param, $profile_filter('status', 'is not equal to', lang('Hidden'))),
            'empty_message'       => lang('No photos shared under this name yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', '', lang('Rows'), array($row(array($loop(array($profile_post_tile('ayla')))), '3')), $show_when('has_filter_value')),
            $para(lang('Choose a member to see the photos they shared.'), 'text-body-secondary mb-0', $show_when('no_filter_value')),
        )),
    ),

    // The staff list: every profile with its author name and a link to the
    // member's page, searchable.
    'staff_members_list' => array(
        'page'   => 'staff_members',
        'slug'   => lang('form-list'),
        'config' => $profile_list_config(array(
            'items_per_page' => 30,
            'search_enabled' => true,
            'search_fields'  => array('display_name', 'city', 'interests', 'bio', 'website'),
            'search_width'   => 'half',
            'search_label'   => lang('Search the profiles'),
            'empty_message'  => lang('No member has sent a profile yet.'),
        )),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Rows'), array($loop(array(
                $el('div', 'list-group-item d-flex flex-wrap align-items-center gap-3 py-3', lang('Profile Row'), array(
                    $profile_avatar('psc-avatar', 'tom'),
                    $el('div', 'flex-grow-1', lang('Text'), array(
                        $el('p', 'fw-semibold mb-0', lang('Name shown to members'), array(), array_merge(array('text' => $members['tom']['name']), $bind('display_name'))),
                        $el('p', 'small text-body-secondary mb-0', lang('Author'), array(
                            $span(lang('Sent by'), 'me-1', lang('Label')),
                            $span($members['tom']['name'], '', lang('Value'), $bind('submitter_name')),
                            $span('·', 'mx-1', lang('Separator')),
                            $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                    )),
                    $link(lang('Open public page'), '#', 'btn btn-sm btn-outline-primary rounded-pill', array(),
                        $page_link('member', array('member' => 'submitter_name'))),
                )),
            )))),
        )),
    ),
);

return array('pages' => $profile_pages, 'widgets' => $profile_widgets);
