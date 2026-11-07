<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", its artists and works: the staff pages
 * that add an artist and a work (the Artists and Works forms, collections,
 * with the sample records written in when they are made), the artists page
 * with its medium filter, the artist's page with the artist's works, the
 * works page with its artist and medium filters, the work's page with the
 * way to an enquiry, and the artists the home page places. Included after
 * parts.php and cards.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An artist is a record of the Artists form, a work a record of the Works
 * form. A work's artist is a reference into the Artists form: the field
 * holds the artist's code, the rows print the artist's name through the
 * reference, and the artist's page lists the artist's own works (the
 * dynamic value "shown record"). ?artist= on the works page takes that
 * code (or an address name) and the page prints the artist's name; the
 * artist's page and each work send it. Codes are made when the template is
 * installed, so no link bar of artists is built; the medium bars are, from
 * the Medium fields' answers. A work's exhibition is a reference into the
 * Exhibitions form (exhibitions.php), which lists its works the same way.
 * Availability is the gallery's own field (office use only), shown as a
 * badge.
 *
 * The pieces under "Pieces" ($gl_filter, $gl_page_link, $gl_filter_bar,
 * $gl_record_list) serve the template's other collections too, included
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

$artist_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list: a fixed value, or the value of the address
// parameter $param when one is named.
$gl_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$gl_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of a collection's records: its form page, its record page, the
// order and the words shown when nothing matches.
$gl_record_list = function ($form_page, $detail_page, $empty, $more, $order = 'submitted_date_and_time', $direction = 'DESC') {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer, each opening the
// list page with the filter set.
$gl_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-0');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-0');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The cards of a list: artists in rows of three, works in rows of four.
$artist_grid = function () use ($widget_root, $row, $loop, $artist_card) {
    return $widget_root(array($row(array($loop(array($artist_card()))), '4')));
};
$work_grid = function () use ($widget_root, $row, $loop, $work_card) {
    return $widget_root(array($row(array($loop(array($work_card()))), '4')));
};

// A studio photograph of an artist's page, left out when the record has
// none.
$studio_photo = function ($n, $sample_key) use ($col, $optional_image, $photo) {
    return $col(array(
        $optional_image('photo_' . $n, 'name', 'w-100 h-100 object-fit-cover', 'ratio ratio-4x3 bg-body-tertiary pgl-frame',
            $photo('studio-' . $sample_key . '-' . $n, 900, 675), lang('Studio Photograph')),
    ), '4');
};

// The medium of a work: its artist's main medium, in the words of the
// Medium list.
$work_medium = function ($key) use ($works, $artists, $mediums) {
    return $mediums[$artists[$works[$key]['artist']]['medium']];
};

// ── Pages ───────────────────────────────────────────────────────────────

// The artists, in the order of their names, narrowed by medium from the
// address.
$artist_pages[] = array(
    'key'              => 'artists',
    'name'             => lang('artists'),
    'folder'           => 'public',
    'title'            => lang('Artists'),
    'meta_description' => lang('The artists the gallery represents: painters, sculptors, photographers and makers, with their works and exhibitions.'),
    'tree'             => $page(array(
        $page_header(lang('Artists'), lang('The artists we represent and follow from one studio to the next. Choose a medium to narrow the list.')),
        $el('section', 'py-5', lang('Artists'), array(
            $container(array(
                $gl_filter_bar('artists', lang('Art Form'), 'medium', $medium_titles),
                $widget('artists_list'),
            )),
        ), $searchable('gl-artists', lang('artists, painters, sculptors, photographers, ceramicists, studio, represented'))),
    )),
);

// One artist: the record, then the artist's works.
$artist_pages[] = array(
    'key'              => 'artist',
    'name'             => lang('artist'),
    'folder'           => 'public',
    'title'            => lang('Artist'),
    'meta_description' => lang('An artist of the gallery: the statement, the story, the studio and the works you can see and ask about.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('artist_detail'),
        $el('section', 'py-5 border-top', lang('Works'), array(
            $container(array(
                $section_header(lang('In the gallery'), lang('Works by the artist'), lang('All works'), '{{page:works}}'),
                $widget('artist_works'),
            )),
        )),
    )),
);

// The works, the newest first, narrowed by artist and medium from the
// address.
$artist_pages[] = array(
    'key'              => 'works',
    'name'             => lang('artworks'),
    'folder'           => 'public',
    'title'            => lang('Works'),
    'meta_description' => lang('Works by the gallery\'s artists: paintings, sculpture, photographs, works on paper and ceramics, with their price and availability.'),
    'tree'             => $page(array(
        $page_header(lang('Works'), lang('Every work we can show you, with its size, its price and whether it is still available. Ask about any of them.')),
        $el('section', 'py-5', lang('Works'), array(
            $container(array(
                $gl_filter_bar('works', lang('Art Form'), 'medium', $medium_titles),
                $widget('works_list'),
                $para(lang('Where a work says "Price on request", the price is given with an enquiry. Nothing is sold online: every work is sold through the gallery.'),
                    'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('gl-works', lang('works, artworks, paintings, sculpture, photographs, prints, drawings, ceramics, prices, available'))),
    )),
);

// One work.
$artist_pages[] = array(
    'key'              => 'work',
    'name'             => lang('artwork'),
    'folder'           => 'public',
    'title'            => lang('Artwork'),
    'meta_description' => lang('A work in the gallery: the artist, the technique, the size, the price and the way to ask about it or see it in person.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('work_detail'),
    )),
);

// The staff page that adds an artist. The form is the Artists collection;
// the sample artists are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_artists = array();
foreach ($artists as $key => $a) {
    $sample_artists[] = array(
        'name'      => $a['name'],
        'medium'    => $mediums[$a['medium']],
        'based'     => $a['based'],
        'summary'   => $a['summary'],
        'statement' => $a['statement'],
        'bio'       => $a['bio'],
        'photo'     => $portrait($a['name'], 900),
        'photo_1'   => $photo('studio-' . $key . '-1', 1200, 900),
        'photo_2'   => $photo('studio-' . $key . '-2', 1200, 900),
        'photo_3'   => $photo('studio-' . $key . '-3', 1200, 900),
    );
}
$artist_pages[] = array(
    'key'     => 'artist_new',
    'name'    => lang('new-artist'),
    'folder'  => 'private',
    'title'   => lang('New Artist'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Artists'),
        'collection'           => 1,
        'confirmation_message' => lang('The artist is published.'),
        'confirmation_page_id' => '{{tab:artists}}',
        'sample_records'       => $sample_artists,
        // The portrait is the artist's share image
        // (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Artist'), lang('Add an artist the gallery represents with a portrait, a statement and studio photographs; the artist appears in the list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('artist_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The staff page that adds a work. The form is the Works collection; the
// sample works are written in when the form is made, after the artists and
// exhibitions they name by title (the seeding finds each record's code).
$sample_works = array();
foreach ($works as $key => $w) {
    $sample = array(
        'title'        => $w['title'],
        'artist'       => $artists[$w['artist']]['name'],
        'exhibition'   => $w['exhibition'] !== '' ? $exhibitions[$w['exhibition']]['title'] : '',
        'medium'       => $work_medium($key),
        'technique'    => $w['technique'],
        'size'         => $w['size'],
        'price'        => $work_price($key),
        'availability' => $availability[$w['state']],
        'photo'        => $photo('work-' . $key, 1200, 1200),
    );
    // A work shown only on the artist's page names no exhibition.
    if ($sample['exhibition'] === '') unset($sample['exhibition']);
    $sample_works[] = $sample;
}
$artist_pages[] = array(
    'key'     => 'work_new',
    'name'    => lang('new-artwork'),
    'folder'  => 'private',
    'title'   => lang('New Artwork'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Works'),
        'collection'           => 1,
        'confirmation_message' => lang('The work is published.'),
        'confirmation_page_id' => '{{tab:works}}',
        'sample_records'       => $sample_works,
        // The photograph is the work's share image.
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Artwork'), lang('Add a work with its artist, technique, size and price; it appears in the works and on the artist\'s page as soon as you send it. Mark it reserved or sold from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('work_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

// A pick list of a form whose choices are the records of another form
// (a reference): the drawn option is only the prompt.
$reference_select = function ($id, $name, $prompt, $tab, $required) use ($el, $attr) {
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    return $el('select', 'form-select', lang('Field'), array(
        $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', '')))),
    ), array('id' => $id, '_attrs' => $attrs, '_cf' => array('kind' => 'reference', 'ref' => '{{tab:' . $tab . '}}')));
};

$artist_widgets = array(

    // The staff's Artists form: one artist, the portrait and up to three
    // studio photographs.
    'artist_form' => array(
        'page'   => 'artist_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The artist')),
            $field('gl-artist-name', lang('Name'), pg_cf_with_role($input('gl-artist-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('gl-artist-medium', lang('Art Form'), pg_cf_with_role($select('gl-artist-medium', 'medium', lang('Choose an art form'), $medium_titles), 'category'))), '6'),
                $col(array($field('gl-artist-based', lang('Studio'), $input('gl-artist-based', 'text', 'based', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $artists['lena']['based']))), '6'),
            ), '3'),
            $field('gl-artist-summary', lang('Summary'), pg_cf_with_role($input('gl-artist-summary', 'text', 'summary', 'off'), 'description'), 'mb-3',
                lang('One sentence for the cards of the lists.')),
            $form_step(2, lang('In the artist\'s words')),
            $field('gl-artist-statement', lang('Artist\'s Statement'), $textarea('gl-artist-statement', 'statement', 4), 'mb-3',
                lang('What the artist says about the work, in the first person.')),
            $field('gl-artist-bio', lang('Biography'), $textarea('gl-artist-bio', 'bio', 5)),
            $form_step(3, lang('Photographs')),
            $field('gl-artist-photo', lang('Portrait'), pg_cf_with_role($file('gl-artist-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $row(array(
                $col(array($field('gl-artist-photo-1', lang('Studio photograph 1'), $file('gl-artist-photo-1', 'photo_1', 'public', 'image/*'))), '4'),
                $col(array($field('gl-artist-photo-2', lang('Studio photograph 2'), $file('gl-artist-photo-2', 'photo_2', 'public', 'image/*'))), '4'),
                $col(array($field('gl-artist-photo-3', lang('Studio photograph 3'), $file('gl-artist-photo-3', 'photo_3', 'public', 'image/*'))), '4'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff's Works form: one work. The artist is a reference into the
    // Artists form and the exhibition one into the Exhibitions form, whose
    // records are the choices. Availability starts as Available.
    'work_form' => array(
        'page'   => 'work_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The work')),
            $field('gl-work-title', lang('Title'), pg_cf_with_role($input('gl-work-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('gl-work-artist', lang('Artist'), pg_cf_with_role($reference_select('gl-work-artist', 'artist', lang('Choose an artist'), 'artist_new', true), 'category'))), '6'),
                $col(array($field('gl-work-exhibition', lang('Exhibition'), $reference_select('gl-work-exhibition', 'exhibition', lang('Not in an exhibition'), 'exhibition_new', false), 'mb-3',
                    lang('Leave empty for a work shown only on the artist\'s page.'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('gl-work-medium', lang('Art Form'), $select('gl-work-medium', 'medium', lang('Choose an art form'), $medium_titles))), '6'),
                $col(array($field('gl-work-size', lang('Size'), $input('gl-work-size', 'text', 'size', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $works['last_table']['size']))), '6'),
            ), '3'),
            $field('gl-work-technique', lang('Technique'), pg_cf_with_role($input('gl-work-technique', 'text', 'technique', 'off'), 'description'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $works['bridge_2am']['technique'])),
            $form_step(2, lang('Price and availability')),
            $field('gl-work-price', lang('Price'), $input('gl-work-price', 'text', 'price', 'off'), 'mb-3',
                sprintf(lang('As it should read, or %s.'), lang('Price on request'))),
            $field('gl-work-availability', lang('Availability of the work'), $select('gl-work-availability', 'availability', '', $availability_titles, false, $availability_titles[0], array('office_use_only' => true)),
                'mb-3', lang('Shown as a badge on the work\'s card and page.')),
            $form_step(3, lang('Photograph')),
            $field('gl-work-photo', lang('Photograph'), pg_cf_with_role($file('gl-work-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The work alone on a plain wall, in daylight: the picture of the cards and the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every artist in the order of their names, filtered by the address
    // (medium).
    'artists_list' => array(
        'page'   => 'artists',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('artist_new', 'artist', lang('No artist works in this medium yet. Choose another, or see every artist.'), array(
            'items_per_page' => 24,
            'filters'        => array($gl_filter('medium', 'is equal to', '', 'medium')),
        ), 'name', 'ASC'),
        'tree'   => $artist_grid(),
    ),

    // The home page's artists: the first three by name.
    'home_artists' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('artist_new', 'artist', lang('No artist yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
        ), 'name', 'ASC'),
        'tree'   => $artist_grid(),
    ),

    // An artist's page: the works of the artist it shows, the newest first.
    'artist_works' => array(
        'page'   => 'artist',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('work_new', 'work', lang('No work by this artist to show yet. Ask us about the artist\'s studio.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                array('field' => 'artist', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''),
            ),
        )),
        'tree'   => $work_grid(),
    ),

    // Every work, the newest first, filtered by the address (artist and
    // medium), with a search over the words of the works.
    'works_list' => array(
        'page'   => 'works',
        'slug'   => lang('form-list'),
        'config' => $gl_record_list('work_new', 'work', lang('No work in this list yet. Choose another medium, or see every work.'), array(
            'items_per_page' => 24,
            'filters'        => array(
                $gl_filter('artist', 'is equal to', '', 'artist'),
                $gl_filter('medium', 'is equal to', '', 'medium'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'medium', 'technique', 'size'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the works: a title, a technique, a size...'),
        )),
        'tree'   => $work_grid(),
    ),
);

// One artist: the portrait beside the name and the statement, set as a
// pull quote (the palette's Quote: Pull Quote structure), then the story,
// the studio photographs and the ways to the artist's works.
$artist_sample_key = 'lena';
$artist_sample = $artists[$artist_sample_key];
$artist_not_found = lang('This artist could not be found. The page may have moved.');
$artist_widgets['artist_detail'] = array(
    'page'   => 'artist',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:artist_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $artist_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-person-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Artist not found'), 'pgl-display h3 mt-3'),
                $para($artist_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Artists'), '{{page:artists}}', 'btn btn-primary rounded-0 px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Artist'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Artists'), '', lang('Link Text')),
                    ), array('href' => '{{page:artists}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'w-100 h-100 object-fit-cover', 'ratio ratio-1x1 bg-body-tertiary pgl-frame',
                            $portrait($artist_sample['name'], 900), lang('Portrait')),
                        $span($artist_sample['based'], 'pgl-caption d-block small text-body-secondary mt-2', lang('Studio'), $bind('based')),
                    ), '', '5'),
                    $col(array(
                        $span($mediums[$artist_sample['medium']], 'pgl-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('Art Form'), $bind('medium')),
                        $heading('h1', $artist_sample['name'], 'pgl-display display-4 mb-4', $bind('name')),
                        $el('figure', 'mb-5', lang('Pull Quote'), array(
                            $icon('bi-quote', 'd-block mb-2 display-1 lh-1 text-primary'),
                            $el('blockquote', 'blockquote pgl-quote mb-3', lang('Quote'), array(
                                $para($artist_sample['statement'], 'mb-0 fs-4 lh-sm', $bind('statement')),
                            )),
                            $el('figcaption', 'blockquote-footer mb-0 text-body-secondary', lang('Attribution'), array(
                                $span($artist_sample['name'], 'fw-semibold text-body-emphasis', lang('Artist Name'), $bind('name')),
                            )),
                        )),
                        $heading('h2', lang('About the artist'), 'pgl-display h5 mb-2'),
                        $el('div', 'pg-field-value lh-lg mb-4', lang('Biography'), array(), array_merge(array('text' => $artist_sample['bio']), $bind('bio'))),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            // Opens the works on this artist (?artist=, the
                            // record's code, printed as the name).
                            $link(lang('All works by the artist'), '#', 'btn btn-primary rounded-0 px-4', array(),
                                $gl_page_link('works', array('artist' => 'reference_code'))),
                            $link(lang('Ask About a Work'), '{{page:enquire}}', 'btn btn-outline-primary rounded-0 px-4'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 mb-5'),
                $heading('h2', lang('In the studio'), 'pgl-display h4 mb-3'),
                $row(array(
                    $studio_photo(1, $artist_sample_key),
                    $studio_photo(2, $artist_sample_key),
                    $studio_photo(3, $artist_sample_key),
                ), '3'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

// One work: the work alone on a light wall with its label beside it (the
// artist through the reference, the title, the technique, the size), the
// price and availability with the enquiry about this work (?artwork=, its
// title) and a private viewing, then how buying a work goes.
$work_sample_key = 'last_table';
$work_sample = $works[$work_sample_key];
$work_not_found = lang('This work could not be found. It may have been sold or taken off the list.');
$buying_items = array();
foreach ($buying_steps as $n => $s) $buying_items[] = $step($n + 1, $s['title'], $s['text']);
$artist_widgets['work_detail'] = array(
    'page'   => 'work',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:work_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $work_not_found,
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $container(array(
                $icon('bi-easel2', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Work not found'), 'pgl-display h3 mt-3'),
                $para($work_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Works'), '{{page:works}}', 'btn btn-primary rounded-0 px-4'),
            )),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Artwork'), array(
            $el('section', 'py-5 bg-body-tertiary', lang('The Wall'), array(
                $container(array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('Works'), '', lang('Link Text')),
                        ), array('href' => '{{page:works}}')),
                        $edit_button(),
                    )),
                    $row(array(
                        $col(array(
                            $optional_image('photo', 'title', 'w-100 h-100 object-fit-contain p-4', 'ratio ratio-1x1 bg-body shadow-sm pgl-frame',
                                $photo('work-' . $work_sample_key, 1200, 1200), lang('Photograph')),
                        ), '', '8'),
                        $col(array(
                            $el('div', 'pgl-caption border-start border-2 ps-3', lang('Wall Label'), array(
                                $span($artists[$work_sample['artist']]['name'], 'd-block fw-semibold mb-1', lang('Artist'), $artist_ref()),
                                $heading('h1', $work_sample['title'], 'pgl-display h2 fst-italic mb-2', $bind('title')),
                                $span($work_sample['technique'], 'd-block small text-body-secondary', lang('Technique'), $bind('technique')),
                                $span($work_sample['size'], 'd-block small text-body-secondary', lang('Size'), $bind('size')),
                                $span($work_medium($work_sample_key), 'd-block small text-body-secondary', lang('Art Form'), $bind('medium')),
                            )),
                        ), '', '4', '', '12', 'align-self-end'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
            $el('section', 'py-5', lang('Price and Availability'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('p', 'd-flex flex-wrap align-items-center gap-3 mb-3', lang('Price and Availability'), array(
                                $span($work_price($work_sample_key), 'pgl-price fs-3', lang('Price'), $bind('price')),
                                $span($availability[$work_sample['state']], 'badge rounded-0 border text-body', lang('Availability'), $bind('availability')),
                            )),
                            $para(lang('Ask us about this work for its condition, framing and delivery; we hold it for you for a few days while you decide.'), 'text-body-secondary mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                // The enquiry starts with this work chosen
                                // (?artwork=, its title).
                                $link(lang('Ask About This Work'), '#', 'btn btn-primary rounded-0 px-4', array(),
                                    $gl_page_link('enquire', array('artwork' => 'title'))),
                                $link(lang('Book a Private Viewing'), '{{page:viewing}}', 'btn btn-outline-primary rounded-0 px-4'),
                                // The other works of this artist (?artist=,
                                // the code the field holds).
                                $link(lang('More by the artist'), '#', 'btn btn-link link-body-emphasis', array(),
                                    $gl_page_link('works', array('artist' => 'artist'))),
                            )),
                        ), '', '5'),
                        $col(array(
                            $heading('h2', lang('How buying a work goes'), 'pgl-display h5 mb-3'),
                            $el('ol', 'list-unstyled mb-0', lang('Steps'), $buying_items),
                        ), '', '6', '', '12', 'offset-lg-1'),
                    ), '4'),
                )),
            )),
        ), $show_when('record_found')),
    )),
);

return array('pages' => $artist_pages, 'widgets' => $artist_widgets);
