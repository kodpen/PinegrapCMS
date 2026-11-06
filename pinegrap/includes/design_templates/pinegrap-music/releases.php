<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", its releases: the staff page that adds a
 * release (the Releases form, a collection, with the sample records written
 * in when it is made), the discography with its format filter, the release
 * page with the track list, credits, listening links and the fans' words as
 * comments, and the list the home page places. Included after parts.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A release is a record of the Releases form. Format is a pick list whose
 * answers are the words parts.php gives, so a link with ?format= filters
 * the discography by the words a visitor sees. Where the release sits in
 * the band's story (note) and its length are words, never a year. The
 * track list is one line per track. The listening and video addresses are
 * optional: their buttons leave when a release has none. Featured is the
 * band's own choice (office use only): the home page shows the featured
 * releases.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$release_pages = array();

// The cards of a list, in rows of four.
$release_grid = function () use ($widget_root, $row, $loop, $release_card) {
    return $widget_root(array($row(array($loop(array($release_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every release, narrowed by format from the address.
$release_pages[] = array(
    'key'              => 'discography',
    'name'             => lang('discography'),
    'folder'           => 'public',
    'title'            => lang('Discography'),
    'meta_description' => lang('Every album, EP, single and live recording of the band, with the track listing and where to listen.'),
    'tree'             => $page(array(
        $page_header(lang('Discography'), lang('Every record we have made, newest first. Choose a format to narrow the list.')),
        $el('section', 'py-5', lang('Discography'), array(
            $container(array(
                $mu_filter_bar('discography', lang('Format'), 'format', $format_titles),
                $widget('releases_list'),
            )),
        )),
    )),
);

// One release: the record and under it the fans' words, which the band is
// told about.
$release_pages[] = array(
    'key'              => 'release',
    'name'             => lang('release'),
    'folder'           => 'public',
    'title'            => lang('Release Details'),
    'meta_description' => lang('A record of the band: the track listing, the credits, where to listen and what the fans say.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('A fan wrote about a release'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('release_detail'),
        $comments_section(lang('Comments'), 'release_comments'),
    )),
);

// The staff page that adds a release. The form is the Releases collection;
// the sample releases are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()). They are written oldest
// first, so the newest stands first in a list ordered newest first.
$sample_releases = array();
foreach (array_reverse($releases, true) as $key => $r) {
    $sample_releases[] = array(
        'title'    => $r['title'],
        'format'   => $formats[$r['format']]['title'],
        'note'     => $r['note'],
        'length'   => $length_text($key),
        'summary'  => $r['summary'],
        'tracks'   => $tracks_text($key),
        'credits'  => $r['credits'],
        'cover'    => $photo('cover-' . $key, 1200, 1200),
        'featured' => $r['featured'] ? $mu_featured_choices[0] : $mu_featured_choices[1],
    );
}
$release_pages[] = array(
    'key'     => 'release_new',
    'name'    => lang('new-release'),
    'folder'  => 'private',
    'title'   => lang('New Release'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Music Releases'),
        'collection'           => 1,
        'confirmation_message' => lang('The release is published.'),
        'confirmation_page_id' => '{{tab:discography}}',
        'sample_records'       => $sample_releases,
        // The cover is the release's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $mu_form_page(lang('New Release'),
        lang('Add the record with its cover and track listing; it appears in the discography as soon as you send it. Mark it featured from its Edit button.'),
        'release_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$release_widgets = array(

    // The staff's Releases form: one record. Featured starts as No.
    'release_form' => array(
        'page'   => 'release_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The record')),
            $field('mu-rel-title', lang('Release Title'), pg_cf_with_role($input('mu-rel-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('mu-rel-format', lang('Format'), pg_cf_with_role($select('mu-rel-format', 'format', lang('Choose a format…'), $format_titles), 'category'))), '6'),
                $col(array($field('mu-rel-length', lang('Length'), $input('mu-rel-length', 'text', 'length', 'off'), 'mb-3',
                    sprintf(lang('As the cards show it, for example %s.'), $length_text('nightbus')))), '6'),
            ), '3'),
            $field('mu-rel-note', lang('Place in the story'), $input('mu-rel-note', 'text', 'note', 'off'), 'mb-3',
                lang('In words, never a year, for example The second album.')),
            $form_step(2, lang('Words')),
            $field('mu-rel-summary', lang('Summary'), pg_cf_with_role($textarea('mu-rel-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards and the top of the release page.')),
            $field('mu-rel-tracks', lang('Track Listing'), $textarea('mu-rel-tracks', 'tracks', 8), 'mb-3',
                lang('One track on each line, for example 1. Last Ferry — 3:41.')),
            $field('mu-rel-credits', lang('Credits'), $textarea('mu-rel-credits', 'credits', 3, false)),
            $form_step(3, lang('Cover and links')),
            $field('mu-rel-cover', lang('Cover'), pg_cf_with_role($file('mu-rel-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square picture: the cards and the share image.')),
            $row(array(
                $col(array($field('mu-rel-listen', lang('Listening address'), $input('mu-rel-listen', 'url', 'listen_url', 'url', false), 'mb-3',
                    lang('The release on a listening service. Leave empty to hide the button.'))), '6'),
                $col(array($field('mu-rel-video', lang('Video address'), $input('mu-rel-video', 'url', 'video_url', 'url', false), 'mb-3',
                    lang('A video of a song from the release. Leave empty to hide the button.'))), '6'),
            ), '3'),
            $field('mu-rel-featured', lang('Featured'), $select('mu-rel-featured', 'featured', '', $mu_featured_choices, false, $mu_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured release is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every release, filtered by the address (format), newest first.
    'releases_list' => array(
        'page'   => 'discography',
        'slug'   => lang('form-list'),
        'config' => $mu_list_config('release_new', 'release', 'submitted_date_and_time', 'DESC',
            lang('No release in this format yet. Join the mailing list to hear the next one first.'), array(
            'items_per_page' => 12,
            'filters'        => array($mu_filter('format', 'is equal to', '', 'format')),
        )),
        'tree'   => $release_grid(),
    ),

    // The home page's featured releases, newest first, four at most.
    'home_releases' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $mu_list_config('release_new', 'release', 'submitted_date_and_time', 'DESC', lang('No featured releases yet.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'filters'        => array($mu_filter('featured', 'is equal to', $mu_featured_choices[0])),
        )),
        'tree'   => $release_grid(),
    ),

    // The fans' words under a release, newest first, one level of answers
    // so the band can reply.
    'release_comments' => array(
        'page'   => 'release',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'none',
            'layout'     => 'threaded',
            'max_depth'  => 1,
            'per_page'   => 10,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('What the fans say'),
            'empty'     => lang('Nobody has written about this release yet. Tell us which song is yours.'),
            'heading'   => lang('Write about this release'),
            'label'     => lang('Your comment'),
            'sign_in'   => lang('Sign in to write a comment.'),
            'submit'    => lang('Send comment'),
            'watch_box' => lang('Notify me when a new comment is added.'),
            'follow'    => lang('Notify me of new comments'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new comments'),
            'reopen'    => lang('Allow new comments'),
            'reply'     => true,
            'watch'     => true,
        )),
    ),
);

// One release: the cover, the format, the title, its place in the story,
// the length, the summary, the listening and video buttons, the track
// listing and the credits.
$release_sample_key = 'nightbus';
$release_sample = $releases[$release_sample_key];
$release_not_found = lang('This release could not be found. It may have been renamed or taken off the list.');
$release_widgets['release_detail'] = array(
    'page'   => 'release',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:release_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $release_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $mu_not_found('bi-vinyl', lang('Release not found'), $release_not_found, lang('See the discography'), 'discography'),
            $el('article', '', lang('Release Details'), array(
                $mu_toolbar('discography', lang('All Releases')),
                $row(array(
                    $col(array(
                        $optional_image('cover', 'title', 'w-100 h-100 object-fit-cover rounded-4 shadow-sm', 'ratio ratio-1x1',
                            $photo('cover-' . $release_sample_key, 1200, 1200), $release_sample['title']),
                    ), '', '5'),
                    $col(array(
                        $span($formats[$release_sample['format']]['title'], 'pmu-eyebrow badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Format'), $bind('format')),
                        $heading('h1', $release_sample['title'], 'pmu-display display-5 mb-1', $bind('title')),
                        $para($release_sample['note'], 'fw-semibold mb-1', $bind('note')),
                        $para($length_text($release_sample_key), 'small text-body-secondary mb-3', $bind('length')),
                        $para($release_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $mu_record_link(lang('Listen now'), 'listen_url', 'btn btn-primary rounded-pill px-4'),
                            $mu_record_link(lang('Watch the video'), 'video_url', 'btn btn-outline-primary rounded-pill px-4'),
                        )),
                        $track_list($release_sample_key),
                        $mu_long_field(lang('Credits'), 'credits', $release_sample['credits']),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $release_pages, 'widgets' => $release_widgets);
