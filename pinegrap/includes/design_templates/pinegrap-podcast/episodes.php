<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", its episodes: the staff page that adds
 * an episode (the Episodes form, a collection, with the sample episodes
 * written in when it is made), the episodes page with the newest first,
 * the episode page with the way to listen, the chapters, the notes, the
 * transcript and the listeners' comments, and the lists the home page and
 * a podcast's page place. Included after parts.php and podcasts.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An episode is a record of the Episodes form. Its Podcast field is a
 * reference into the Podcasts form: the lists print the podcast's title
 * and cover through it, and a podcast's page lists the episodes pointing
 * at it. The day it comes out is a date: the lists show only the episodes
 * already out (on or before today), so one dated ahead waits for its day.
 * The recording is a file of the Audio field (kept in the episodes
 * folder): the episode page plays it in its own player, which loads
 * nothing until pressed. The episode's address in a podcast app is
 * optional; the player and each button leave when the episode has none. Featured is the network's own
 * choice (office use only): the home page shows the featured episodes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$episode_pages = array();

// The rows of a list, one under the other.
$episode_list = function () use ($widget_root, $loop, $episode_row) {
    return $widget_root(array($loop(array($episode_row()))));
};
// The filter every list shares: only the episodes already out.
$episode_out = $pd_date_filter('aired', 'is less than or equal to');

// ── Pages ───────────────────────────────────────────────────────────────

// Every episode already out, newest first.
$episode_pages[] = array(
    'key'              => 'episodes',
    'name'             => lang('episodes'),
    'folder'           => 'public',
    'title'            => lang('Episodes'),
    'meta_description' => lang('The newest episodes of every podcast on the network, with their guests, notes and the way to listen.'),
    'tree'             => $page(array(
        $page_header(lang('Episodes'), lang('Every episode of every podcast, newest first. Open a podcast to hear only its own.'), array(
            $el('div', 'mt-3', lang('Links'), array(
                $link(lang('All Podcasts'), '{{page:podcasts}}', 'btn btn-sm btn-outline-secondary rounded-pill'),
            )),
        )),
        $el('section', 'py-4', lang('Episodes'), array(
            $container(array(
                $widget('episodes_list'),
            )),
        ), $searchable('pd-episodes', lang('latest, new episode, listen, audio'))),
    )),
);

// One episode: the episode and under it the listeners' comments, which
// the network is told about.
$episode_pages[] = array(
    'key'              => 'episode',
    'name'             => lang('episode'),
    'folder'           => 'public',
    'title'            => lang('Episode Details'),
    'meta_description' => lang('An episode of the network: the way to listen, the chapters, the guests, the notes and what listeners say.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('A listener wrote about an episode'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('episode_detail'),
        $comments_section(lang('Comments'), 'episode_comments'),
    )),
);

// The staff page that adds an episode. The form is the Episodes
// collection; the sample episodes are written in when the form is made,
// after the podcasts they point at (by the podcast's title), so the lists
// do not open empty. Their days are counted back from today.
$episode_notes = function ($key) use ($episodes) {
    $e = $episodes[$key];
    return $e['guests'] !== ''
        ? sprintf(lang('%1$s Our guest: %2$s.'), $e['summary'], $e['guests'])
        : $e['summary'];
};
$sample_transcript = lang('The transcript of the episode goes here: who speaks and what they say, a paragraph for each turn.');
$sample_episodes = array();
foreach (array_reverse($episodes, true) as $key => $e) {
    // A field left empty (no guests) is left out of the record.
    $sample_episodes[] = array_filter(array(
        'title'          => $e['title'],
        'podcast'        => $podcasts[$e['podcast']]['title'],
        'episode_number' => $episode_number_text($key),
        'aired'          => $aired_on($e['days']),
        'length'         => $length_text($key),
        'guests'         => $e['guests'],
        'summary'        => $e['summary'],
        'notes'          => $episode_notes($key),
        'chapters'       => $chapters_text($key),
        'transcript'     => $sample_transcript,
        'featured'       => $e['featured'] ? $pd_featured_choices[0] : $pd_featured_choices[1],
    ), 'strlen');
}
$episode_pages[] = array(
    'key'     => 'episode_new',
    'name'    => lang('new-episode'),
    'folder'  => 'private',
    'title'   => lang('New Episode'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Podcast Episodes'),
        'collection'           => 1,
        'confirmation_message' => lang('The episode is saved. It appears in the lists on the day it comes out.'),
        'confirmation_page_id' => '{{tab:episodes}}',
        'sample_records'       => $sample_episodes,
    ),
    'tree'    => $pd_form_page(lang('New Episode'),
        lang('Add the episode with its recording and its day; it appears in the lists on that day. Mark it featured from its Edit button.'),
        'episode_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$episode_widgets = array(

    // The staff's Episodes form: one record. Featured starts as No.
    'episode_form' => array(
        'page'   => 'episode_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The episode')),
            $field('pd-ep-title', lang('Episode Title'), pg_cf_with_role($input('pd-ep-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                // The podcast it belongs to: a reference into the Podcasts
                // form, whose records are the choices.
                $col(array($field('pd-ep-podcast', lang('Podcast'), pg_cf_with_role($el('select', 'form-select', lang('Field'), array(
                    $el('option', '', lang('Option'), array(), array('text' => lang('Choose a podcast…'), '_attrs' => array($attr('value', '')))),
                ), array('id' => 'pd-ep-podcast', '_attrs' => array($attr('name', 'podcast'), $attr('required', '')),
                         '_cf' => array('kind' => 'reference', 'ref' => '{{tab:podcast_new}}'))), 'category'))), '6'),
                $col(array($field('pd-ep-number', lang('Season and episode'), $input('pd-ep-number', 'text', 'episode_number', 'off'), 'mb-3',
                    sprintf(lang('As the lists show it, for example %s.'), $episode_number_text('kitchen-12')))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('pd-ep-aired', lang('Publishing date'), $input('pd-ep-aired', 'date', 'aired', 'off'), 'mb-3',
                    lang('The lists show the episode from this day on.'))), '6'),
                $col(array($field('pd-ep-length', lang('Length'), $input('pd-ep-length', 'text', 'length', 'off'), 'mb-3',
                    sprintf(lang('As the lists show it, for example %s.'), $length_text('kitchen-12')))), '6'),
            ), '3'),
            $field('pd-ep-guests', lang('Guests of the episode'), $input('pd-ep-guests', 'text', 'guests', 'off', false), 'mb-3',
                lang('Name and what they do, for example Derya Aksoy, chef. Leave empty for none.')),
            $form_step(2, lang('Words')),
            $field('pd-ep-summary', lang('Summary'), pg_cf_with_role($textarea('pd-ep-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the lists and the top of the episode page.')),
            $field('pd-ep-notes', lang('Episode Notes'), $textarea('pd-ep-notes', 'notes', 6, false), 'mb-3',
                lang('What the episode covers, the links and the books mentioned.')),
            $field('pd-ep-chapters', lang('Chapters'), $textarea('pd-ep-chapters', 'chapters', 6, false), 'mb-3',
                sprintf(lang('One chapter on each line, for example %s.'), sprintf(lang('%1$s — %2$s'), '4:10', lang('Closing the restaurant')))),
            $field('pd-ep-transcript', lang('Transcript'), $textarea('pd-ep-transcript', 'transcript', 8, false)),
            $form_step(3, lang('Recording')),
            $field('pd-ep-audio', lang('Recording'), $file('pd-ep-audio', 'audio', 'episodes', 'audio/*'), 'mb-3',
                lang('The episode\'s sound file, an MP3 or M4A. Leave empty to link only to a podcast app.')),
            $field('pd-ep-listen', lang('Podcast app address'), $input('pd-ep-listen', 'url', 'listen_url', 'url', false), 'mb-3',
                lang('The episode in a podcast app. Leave empty to hide the button.')),
            $field('pd-ep-featured', lang('Featured'), $select('pd-ep-featured', 'featured', '', $pd_featured_choices, false, $pd_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured episode is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every episode already out, newest first.
    'episodes_list' => array(
        'page'   => 'episodes',
        'slug'   => lang('form-list'),
        'config' => $pd_list_config('episode_new', 'episode', 'aired', 'DESC',
            lang('No episode is out yet. Follow the podcasts to hear the first one.'), array(
            'items_per_page' => 10,
            'filters'        => array($episode_out),
        )),
        'tree'   => $episode_list(),
    ),

    // The home page's featured episodes already out, newest first, three
    // at most.
    'home_episodes' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $pd_list_config('episode_new', 'episode', 'aired', 'DESC', lang('No featured episodes yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($pd_filter('featured', 'is equal to', $pd_featured_choices[0]), $episode_out),
        )),
        'tree'   => $episode_list(),
    ),

    // A podcast's page: the episodes of the podcast it shows, already out,
    // newest first.
    'podcast_episodes' => array(
        'page'   => 'podcast',
        'slug'   => lang('form-list'),
        'config' => $pd_list_config('episode_new', 'episode', 'aired', 'DESC', lang('This podcast has no episode out yet.'), array(
            'items_per_page' => 10,
            'filters'        => array($pd_shown_filter('podcast'), $episode_out),
        )),
        'tree'   => $episode_list(),
    ),

    // The listeners' words under an episode, newest first, one level of
    // answers so the host can reply.
    'episode_comments' => array(
        'page'   => 'episode',
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
            'title'     => lang('What listeners say'),
            'empty'     => lang('Nobody has written about this episode yet. Tell the host what you thought.'),
            'heading'   => lang('Write about this episode'),
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

// One episode: its podcast's cover and title (through the reference), the
// season and number, the title, the day, the length, the guests, the
// summary, the way to listen, the chapters, the notes and the transcript.
$episode_sample_key = 'kitchen-12';
$episode_sample = $episodes[$episode_sample_key];
$episode_podcast = $podcasts[$episode_sample['podcast']];
$episode_not_found = lang('This episode could not be found. It may have been renamed or taken off the list.');
$episode_widgets['episode_detail'] = array(
    'page'   => 'episode',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:episode_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $episode_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $pd_not_found('bi-soundwave', lang('Episode not found'), $episode_not_found, lang('See the latest episodes'), 'episodes'),
            $el('article', '', lang('Episode Details'), array(
                $pd_toolbar('episodes', lang('All Episodes')),
                $row(array(
                    $col(array(
                        $el('span', 'pg-field-value d-block ratio ratio-1x1 rounded-4 overflow-hidden shadow-sm bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('cover-' . $episode_sample['podcast'], 800, 800), $episode_podcast['title'], 'w-100 h-100 object-fit-cover',
                                $pd_podcast_ref(array('src' => 'cover', 'alt' => 'title'))),
                        )),
                    ), '', '4'),
                    $col(array(
                        $el('p', 'd-flex flex-wrap gap-2 mb-2', lang('Episode Facts'), array(
                            $span($episode_podcast['title'], 'ppd-eyebrow badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Podcast'),
                                $pd_podcast_ref(array('text' => 'title'))),
                            $span($episode_number_text($episode_sample_key), 'small fw-semibold text-body-secondary', lang('Episode Number'), $bind('episode_number')),
                        )),
                        $heading('h1', $episode_sample['title'], 'ppd-display display-6 mb-2', $bind('title')),
                        $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mb-3', lang('Episode Details'), array(
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Date'), array(
                                $icon('bi-calendar3', 'text-primary'),
                                $span($aired_on($episode_sample['days']), 'ppd-date', lang('Date'), $bind('aired')),
                            )),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Length'), array(
                                $icon('bi-clock', 'text-primary'),
                                $span($length_text($episode_sample_key), '', lang('Value'), $bind('length')),
                            )),
                            $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1', lang('Guests of the episode'), array(
                                $icon('bi-person', 'text-primary'),
                                $span($episode_sample['guests'], 'pg-field-value', lang('Value'), $bind('guests')),
                            )),
                        )),
                        $para($episode_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $episode_player($episode_sample['title']),
                        $listen_buttons(),
                    ), '', '8'),
                ), '4', '', 'g-lg-5 mb-4'),
                $row(array(
                    $col(array(
                        $pd_long_field(lang('Episode Notes'), 'notes', $episode_notes($episode_sample_key)),
                        $pd_long_field(lang('Transcript'), 'transcript', $sample_transcript),
                    ), '', '8'),
                    $col(array(
                        $chapter_list($episode_sample_key),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $episode_pages, 'widgets' => $episode_widgets);
