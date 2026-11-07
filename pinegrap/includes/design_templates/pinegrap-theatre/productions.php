<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its productions: the staff page that adds a
 * production (the Productions form, a collection, with the sample
 * productions written in when it is made), the season with its genre and
 * state filters, the production's own page with the audience's comments,
 * and the list the home page places. Included after parts.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A production is a record of the Productions form. Its genre and its
 * state are pick lists whose answers are the words parts.php gives, so a
 * link with ?genre= or ?status= (the filter rows, the footer, the genre
 * cards, the production page) filters the season by the words a visitor
 * sees; the theatre moves a production on by editing its state. The run is
 * words: the theatre writes its own in each record, the sample never names
 * a year; the dates of each performance are records of the Performances
 * form, listed on the production's page (production_performances, with the
 * performances). The home page shows the productions now playing. The
 * newest added comes first.
 *
 * The pieces under "Pieces" ($th_filter, $th_page_link, $th_filter_bar,
 * $th_record_list) serve the template's other collections too, included
 * after this file in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$production_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$th_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$th_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of a collection's records: its form page, its record page, the
// newest first, and the words shown when nothing matches.
$th_record_list = function ($form_page, $detail_page, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer, each opening the
// list page with the filter set.
$th_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-3', lang('Filters'), $children);
};

// The production cards of a list, in rows of three.
$production_grid = function () use ($widget_root, $row, $loop, $production_card) {
    return $widget_root(array($row(array($loop(array($production_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The season: every production, narrowed by state and genre from the
// address.
$production_pages[] = array(
    'key'              => 'season',
    'name'             => lang('season'),
    'folder'           => 'public',
    'title'            => lang('The Season'),
    'meta_description' => lang('The productions playing now at the theatre, the ones coming to the stage and the ones of past seasons.'),
    'tree'             => $page(array(
        $page_header(lang('The Season'), lang('What is playing now, what is coming to the stage and what we have shown before. Choose a state or a genre to narrow the list.')),
        $el('section', 'py-5', lang('The Season'), array(
            $container(array(
                $th_filter_bar('season', lang('Show'), 'status', $production_statuses),
                $el('div', 'mb-4', lang('Filters'), array($th_filter_bar('season', lang('Genre'), 'genre', $genre_titles))),
                $widget('season_list'),
            )),
        ), $searchable('th-season', lang('what\'s on, shows, plays, current, upcoming, past, programme'))),
    )),
);

// One production: its record, its performances and under them the
// audience's words, which the theatre is told about.
$production_pages[] = array(
    'key'              => 'production',
    'name'             => lang('production-details'),
    'folder'           => 'public',
    'title'            => lang('Production Details'),
    'meta_description' => lang('A production at the theatre: its run, its stage, who made it and the dates of its performances.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('An audience member wrote about a production'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('production_detail'),
        $el('section', 'pb-5', lang('Performances'), array(
            $container(array(
                $section_header(lang('Dates'), lang('Performances of this production'), lang('All Performances'), '{{page:whats_on}}'),
                $widget('production_performances'),
            )),
        )),
        $comments_section(lang('Comments'), 'production_comments'),
    )),
);

// The staff page that adds a production. The form is the Productions
// collection; the sample productions are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_productions = array();
foreach ($productions as $key => $p) {
    $sample_productions[] = array(
        'title'        => $p['title'],
        'genre'        => $production_genre($key),
        'status'       => $production_status($key),
        'stage'        => $production_stage($key),
        'run'          => $p['run'],
        'running_time' => $p['length'],
        'age_guidance' => $p['age'],
        'credits'      => $p['credits'],
        'summary'      => $p['summary'],
        'cast'         => $details_text($p['cast']),
        'photo_1'      => $photo('production-' . $key, 1200, 900),
        'photo_2'      => $photo('production-' . $key . '-2', 1200, 900),
        'photo_3'      => $photo('production-' . $key . '-3', 1200, 900),
    );
}
$production_pages[] = array(
    'key'     => 'production_new',
    'name'    => lang('new-production'),
    'folder'  => 'private',
    'title'   => lang('New Production'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Productions'),
        'collection'           => 1,
        'confirmation_message' => lang('The production is published.'),
        'confirmation_page_id' => '{{tab:season}}',
        'sample_records'       => $sample_productions,
        // The first photo is the production's share image
        // (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Production'), lang('Add the production with its run, stage and photos; it appears in the season as soon as you send it. Add its dates as performances, and change its state from its Edit button as the run opens and closes.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('production_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$production_widgets = array(

    // The staff's Productions form: one production. The state starts as
    // Coming to the Stage, the usual state of a new production.
    'production_form' => array(
        'page'   => 'production_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The production')),
            $field('th-production-title', lang('Production Title'), pg_cf_with_role($input('th-production-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('th-production-genre', lang('Genre'), pg_cf_with_role($select('th-production-genre', 'genre', lang('Choose a genre'), $genre_titles), 'category'))), '6'),
                $col(array($field('th-production-status', lang('Status'), $select('th-production-status', 'status', '', $production_statuses, true, $production_status_titles['soon']))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('th-production-stage', lang('Theatre Stage'), $select('th-production-stage', 'stage', '', $stage_titles, true, $stage_titles[0]))), '6'),
                $col(array($field('th-production-run', lang('Run Dates'), $input('th-production-run', 'text', 'run', 'off'), 'mb-3',
                    sprintf(lang('The run in words, for example %s.'), $productions['lighthouse']['run']))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('th-production-length', lang('Running Time'), $input('th-production-length', 'text', 'running_time', 'off', false))), '6'),
                $col(array($field('th-production-age', lang('Age Guidance'), $input('th-production-age', 'text', 'age_guidance', 'off', false))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('th-production-credits', lang('Credits'), $input('th-production-credits', 'text', 'credits', 'off', false), 'mb-3',
                lang('Who wrote and directed it, in a line.')),
            $field('th-production-summary', lang('Summary'), pg_cf_with_role($textarea('th-production-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('th-production-cast', lang('Cast and Creative Team'), $textarea('th-production-cast', 'cast', 5, false), 'mb-3', lang('One point on each line.')),
            $form_step(3, lang('Photos')),
            $field('th-production-photo-1', lang('Main Photo'), pg_cf_with_role($file('th-production-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('th-production-photo-2', lang('Second Photo'), $file('th-production-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('th-production-photo-3', lang('Third Photo'), $file('th-production-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The season: every production, filtered by the address (state and
    // genre), the newest first, with a search over their words.
    'season_list' => array(
        'page'   => 'season',
        'slug'   => lang('form-list'),
        'config' => $th_record_list('production_new', 'production', lang('No production in this list yet. Choose another, or see the whole season.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $th_filter('status', 'is equal to', '', 'status'),
                $th_filter('genre', 'is equal to', '', 'genre'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'genre', 'status', 'stage', 'run', 'credits', 'summary', 'cast'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the season: a title, a writer, an actor...'),
        )),
        'tree'   => $production_grid(),
    ),

    // The home page's productions now playing, three at most.
    'home_productions' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $th_record_list('production_new', 'production', lang('Nothing is playing just now. See what is coming to the stage.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($th_filter('status', 'is equal to', $production_status_titles['now'])),
        )),
        'tree'   => $production_grid(),
    ),

    // The audience's words under a production, newest first, one level of
    // answers so the theatre can reply.
    'production_comments' => array(
        'page'   => 'production',
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
            'title'     => lang('What the audience says'),
            'empty'     => lang('Nobody has written about this production yet. Seen it? Tell us what you thought.'),
            'heading'   => lang('Write about this production'),
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

// One production: the genre and state, the title, the summary, the facts,
// the credits, the photos, the cast and beside them the box office, the
// way to its performances and the other productions of the same genre.
$production_sample_key = 'lighthouse';
$production_sample = $productions[$production_sample_key];
$production_not_found = lang('This production could not be found. Its run may have ended or it was taken off the list.');
$production_widgets['production_detail'] = array(
    'page'   => 'production',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:production_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $production_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-mask', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Production not found'), 'h3 fw-semibold mt-3'),
                $para($production_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('The Whole Season'), '{{page:season}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Production Details'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('The Whole Season'), '', lang('Link Text')),
                    ), array('href' => '{{page:season}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Genre and Status'), array(
                    $span($production_genre($production_sample_key), 'pth-eyebrow small text-uppercase fw-semibold text-primary', lang('Genre'), $bind('genre')),
                    $status_badge($production_status($production_sample_key)),
                )),
                $heading('h1', $production_sample['title'], 'pth-display display-5 mb-2', $bind('title')),
                $para($production_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $production_facts($production_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-2'),
                $para($production_sample['credits'], 'small fw-semibold mb-4 pg-hide-if-empty', $bind('credits')),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'object-fit-cover', 'ratio ratio-4x3 mb-4',
                            $photo('production-' . $production_sample_key, 1200, 900), lang('Main Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Cast and Creative Team'), array(
                            $heading('h2', lang('Cast and creative team'), 'pth-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $details_text($production_sample['cast'])), $bind('cast'))),
                        )),
                        $row(array(
                            $col(array($optional_image('photo_2', 'title', 'object-fit-cover', 'ratio ratio-4x3',
                                $photo('production-' . $production_sample_key . '-2', 1200, 900), lang('Second Photo'))), '6'),
                            $col(array($optional_image('photo_3', 'title', 'object-fit-cover', 'ratio ratio-4x3',
                                $photo('production-' . $production_sample_key . '-3', 1200, 900), lang('Third Photo'))), '6'),
                        ), '3'),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Box Office Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Book your seats'), 'pth-display h5 mb-3'),
                                $hours_list('mb-3'),
                                $para($theatre['phone'], 'fw-semibold mb-3'),
                                // Opens the performances of this production
                                // (?production=).
                                $link(lang('See the Dates'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $th_page_link('whats_on', array('production' => 'title'))),
                                $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-secondary w-100 mb-2'),
                                // Opens the season on this production's
                                // genre (?genre=).
                                $link(lang('More shows like this'), '#', 'btn btn-link w-100', array(),
                                    $th_page_link('season', array('genre' => 'genre'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $production_pages, 'widgets' => $production_widgets);
