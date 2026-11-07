<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", its podcasts: the staff page that adds
 * a podcast (the Podcasts form, a collection, with the sample podcasts
 * written in when it is made), the podcasts page with its category filter,
 * the podcast page with its newest episodes, and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A podcast is a record of the Podcasts form. Category is a pick list
 * whose answers are the words parts.php gives, so a link with ?category=
 * filters the podcasts by the words a visitor sees. The episodes point at
 * their podcast through a reference (episodes.php); the podcast page lists
 * those of the podcast it shows. The app addresses are optional: their
 * buttons leave when a podcast has none. Featured is the network's own
 * choice (office use only): the home page shows the featured podcasts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$podcast_pages = array();

// The cards of a list, in rows of four.
$podcast_grid = function () use ($widget_root, $row, $loop, $podcast_card) {
    return $widget_root(array($row(array($loop(array($podcast_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every podcast, narrowed by category from the address.
$podcast_pages[] = array(
    'key'              => 'podcasts',
    'name'             => lang('podcasts'),
    'folder'           => 'public',
    'title'            => lang('Podcasts'),
    'meta_description' => lang('Every podcast of the network, with its host, its subject and when new episodes come out.'),
    'tree'             => $page(array(
        $page_header(lang('Podcasts'), lang('Every podcast we make. Choose a category to narrow the list.')),
        $el('section', 'py-5', lang('Podcasts'), array(
            $container(array(
                $pd_filter_bar('podcasts', lang('Category'), 'category', $category_titles),
                $widget('podcasts_list'),
            )),
        ), $searchable('pd-podcasts', lang('shows, series, programmes, channels'))),
    )),
);

// One podcast: the podcast and under it its episodes, newest first.
$podcast_pages[] = array(
    'key'              => 'podcast',
    'name'             => lang('podcast'),
    'folder'           => 'public',
    'title'            => lang('Podcast Details'),
    'meta_description' => lang('A podcast of the network: what it is about, who hosts it, where to follow it and its newest episodes.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('podcast_detail'),
        $el('section', 'pb-5', lang('Episodes'), array(
            $container(array(
                $section_header(lang('Listen'), lang('Episodes'), lang('All Episodes'), '{{page:episodes}}'),
                $widget('podcast_episodes'),
            )),
        )),
    )),
);

// The staff page that adds a podcast. The form is the Podcasts collection;
// the sample podcasts are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()), in the order parts.php
// gives them, which the lists keep.
$sample_podcasts = array();
foreach ($podcasts as $key => $p) {
    $sample_podcasts[] = array(
        'title'     => $p['title'],
        'category'  => $categories[$p['category']]['title'],
        'hosted_by' => sprintf(lang('Hosted by %s'), $hosts[$p['host']]['name']),
        'schedule'  => $p['schedule'],
        'summary'   => $p['summary'],
        'about'     => $hosts[$p['host']]['bio'],
        'cover'     => $photo('cover-' . $key, 1200, 1200),
        'featured'  => $p['featured'] ? $pd_featured_choices[0] : $pd_featured_choices[1],
    );
}
$podcast_pages[] = array(
    'key'     => 'podcast_new',
    'name'    => lang('new-podcast'),
    'folder'  => 'private',
    'title'   => lang('New Podcast'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Network Podcasts'),
        'collection'           => 1,
        'confirmation_message' => lang('The podcast is published.'),
        'confirmation_page_id' => '{{tab:podcasts}}',
        'sample_records'       => $sample_podcasts,
        // The cover is the podcast's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $pd_form_page(lang('New Podcast'),
        lang('Add the podcast with its cover and its day; its episodes point at it. Mark it featured from its Edit button.'),
        'podcast_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$podcast_widgets = array(

    // The staff's Podcasts form: one record. Featured starts as No.
    'podcast_form' => array(
        'page'   => 'podcast_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The podcast')),
            $field('pd-pod-title', lang('Podcast Title'), pg_cf_with_role($input('pd-pod-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('pd-pod-category', lang('Category'), pg_cf_with_role($select('pd-pod-category', 'category', lang('Choose a category…'), $category_titles), 'category'))), '6'),
                $col(array($field('pd-pod-host', lang('Host line'), $input('pd-pod-host', 'text', 'hosted_by', 'off'), 'mb-3',
                    sprintf(lang('As the cards show it, for example %s.'), sprintf(lang('Hosted by %s'), $hosts['selin']['name'])))), '6'),
            ), '3'),
            $field('pd-pod-schedule', lang('Publishing day'), $input('pd-pod-schedule', 'text', 'schedule', 'off'), 'mb-3',
                lang('In words, for example New episodes every Tuesday.')),
            $form_step(2, lang('Words')),
            $field('pd-pod-summary', lang('Summary'), pg_cf_with_role($textarea('pd-pod-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards and the top of the podcast page.')),
            $field('pd-pod-about', lang('About the podcast'), $textarea('pd-pod-about', 'about', 5, false)),
            $form_step(3, lang('Cover and apps')),
            $field('pd-pod-cover', lang('Cover'), pg_cf_with_role($file('pd-pod-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A square picture, the podcast\'s artwork: the cards and the share image.')),
            $row(array(
                $col(array($field('pd-pod-apple', lang('Apple Podcasts address'), $input('pd-pod-apple', 'url', 'apple_url', 'url', false), 'mb-3',
                    lang('Leave empty to hide the button.'))), '4'),
                $col(array($field('pd-pod-spotify', lang('Spotify address'), $input('pd-pod-spotify', 'url', 'spotify_url', 'url', false), 'mb-3',
                    lang('Leave empty to hide the button.'))), '4'),
                $col(array($field('pd-pod-feed', lang('Feed address'), $input('pd-pod-feed', 'url', 'feed_url', 'url', false), 'mb-3',
                    lang('The RSS feed your podcast host gives. Leave empty to hide the button.'))), '4'),
            ), '3'),
            $field('pd-pod-featured', lang('Featured'), $select('pd-pod-featured', 'featured', '', $pd_featured_choices, false, $pd_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured podcast is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every podcast, filtered by the address (category), in the order
    // they were added.
    'podcasts_list' => array(
        'page'   => 'podcasts',
        'slug'   => lang('form-list'),
        'config' => $pd_list_config('podcast_new', 'podcast', 'submitted_date_and_time', 'ASC',
            lang('No podcast in this category yet.'), array(
            'items_per_page' => 12,
            'filters'        => array($pd_filter('category', 'is equal to', '', 'category')),
        )),
        'tree'   => $podcast_grid(),
    ),

    // The home page's featured podcasts, four at most.
    'home_podcasts' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $pd_list_config('podcast_new', 'podcast', 'submitted_date_and_time', 'ASC', lang('No featured podcasts yet.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
            'filters'        => array($pd_filter('featured', 'is equal to', $pd_featured_choices[0])),
        )),
        'tree'   => $podcast_grid(),
    ),
);

// One podcast: the cover, the category, the title, the host, the day, the
// summary, the apps and the words about it.
$podcast_sample_key = 'kitchen';
$podcast_sample = $podcasts[$podcast_sample_key];
$podcast_not_found = lang('This podcast could not be found. It may have been renamed or taken off the network.');
$podcast_widgets['podcast_detail'] = array(
    'page'   => 'podcast',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:podcast_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $podcast_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $pd_not_found('bi-mic', lang('Podcast not found'), $podcast_not_found, lang('See all podcasts'), 'podcasts'),
            $el('article', '', lang('Podcast Details'), array(
                $pd_toolbar('podcasts', lang('All Podcasts')),
                $row(array(
                    $col(array(
                        $optional_image('cover', 'title', 'w-100 h-100 object-fit-cover rounded-4 shadow-sm', 'ratio ratio-1x1',
                            $photo('cover-' . $podcast_sample_key, 1200, 1200), $podcast_sample['title']),
                    ), '', '4'),
                    $col(array(
                        $span($categories[$podcast_sample['category']]['title'], 'ppd-eyebrow badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Category'), $bind('category')),
                        $heading('h1', $podcast_sample['title'], 'ppd-display display-5 mb-1', $bind('title')),
                        $para(sprintf(lang('Hosted by %s'), $hosts[$podcast_sample['host']]['name']), 'fw-semibold mb-1', $bind('hosted_by')),
                        $para($podcast_sample['schedule'], 'small text-body-secondary mb-3', $bind('schedule')),
                        $para($podcast_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $pd_record_link('Apple Podcasts', 'apple_url', 'btn btn-primary rounded-pill px-4'),
                            $pd_record_link('Spotify', 'spotify_url', 'btn btn-outline-primary rounded-pill px-4'),
                            $pd_record_link(lang('RSS Feed'), 'feed_url', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                        $pd_long_field(lang('About the podcast'), 'about', $hosts[$podcast_sample['host']]['bio']),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $podcast_pages, 'widgets' => $podcast_widgets);
