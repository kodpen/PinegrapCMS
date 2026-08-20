<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// SEO score engine.
//
// page, products and product_groups all carry seo_score / seo_analysis /
// seo_analysis_current; this library is what computes them. It lives in its
// own file rather than functions.php on purpose: the evaluator is only needed
// on the list screens, the edit screens and the nightly job, and functions.php
// is loaded on every request of the site.
//
// The scoring model is applicable-weight normalization. Every check has a
// weight and an applicability condition; the final score is
// earned / applicable * 100. A check that does not apply to a record (for
// example keywords on a page that is excluded from site search) leaves the
// denominator instead of counting as a failure, so exemptions need no special
// cases anywhere else.
//
// The character thresholds (title 50-60, description 150-160) are the same
// numbers initSeoCounters() paints in the edit screens. If they are ever
// changed they must change in both places, otherwise the edit screen shows
// green while the list screen deducts points.

// Bit positions for seo_flags. The bitmask exists so list screens can filter
// on one specific problem ("in the sitemap, no title") with a single AND on
// an INT column instead of parsing the seo_analysis JSON per row. Bits for
// the HTML structure and link checks are reserved here so those features can
// ship without a schema change.
function pg_seo_flag_defs()
{
    static $defs = null;

    if ($defs === null) {
        $defs = array(
            'title_missing'        => 1,
            'title_short'          => 2,
            'title_long'           => 4,
            'title_duplicate'      => 8,
            'title_filler'         => 16,
            'desc_missing'         => 32,
            'desc_short'           => 64,
            'desc_long'            => 128,
            'desc_duplicate'       => 256,
            'keywords_missing'     => 512,
            'keywords_thin'        => 1024,
            'keywords_stuffed'     => 2048,
            'url_non_ascii'        => 4096,
            'sitemap_blocked'      => 8192,
            'source_thin'          => 16384,
            'struct_error'         => 32768,
            'struct_warning'       => 65536,
            'no_h1'                => 131072,
            'multi_h1'             => 262144,
            'img_no_alt'           => 524288,
            'thin_content'         => 1048576,
            'broken_internal_link' => 2097152,
            'orphan'               => 4194304,
            'speed_slow'           => 8388608,
        );
    }

    return $defs;
}

// Human labels for the flag bits, ordered by severity so the list screens can
// show the worst problems first. Only the bits the meta evaluator sets today
// are listed; structure and link bits get their labels when those checks ship.
function pg_seo_flag_labels($flags, $max = 0)
{
    $defs = pg_seo_flag_defs();

    // Order is severity order, not bit order: the caller usually asks for
    // the worst two or three, and what it gets is whatever comes first here.
    // Missing text ranks above structural faults, which rank above things
    // that are merely suboptimal.
    $ordered = array(
        'title_missing'    => lang('Title is missing'),
        'desc_missing'     => lang('Description is missing'),
        'no_h1'            => lang('No H1 heading'),
        'struct_error'     => lang('HTML structure errors'),
        'broken_internal_link' => lang('Broken internal links'),
        'orphan'           => lang('Nothing links to this page'),
        'sitemap_blocked'  => lang('Marked for site map but not publicly accessible'),
        'url_non_ascii'    => lang('Name contains non-ASCII characters'),
        'title_duplicate'  => lang('Title is duplicated'),
        'desc_duplicate'   => lang('Description is duplicated'),
        'multi_h1'         => lang('More than one H1 heading'),
        'img_no_alt'       => lang('Images without alt text'),
        'thin_content'     => lang('Thin content'),
        'speed_slow'       => lang('Server response is slow'),
        'keywords_missing' => lang('Keywords are missing'),
        'title_long'       => lang('Title is too long'),
        'desc_long'        => lang('Description is too long'),
        'title_short'      => lang('Title is too short'),
        'desc_short'       => lang('Description is too short'),
        'keywords_thin'    => lang('Too few keywords'),
        'keywords_stuffed' => lang('Too many keywords'),
        'title_filler'     => lang('Title is a filler value'),
        'source_thin'      => lang('Source content is thin'),
    );

    $labels = array();

    foreach ($ordered as $code => $label) {
        if ($flags & $defs[$code]) {
            $labels[] = $label;

            if ($max && (count($labels) >= $max)) {
                break;
            }
        }
    }

    return $labels;
}

// Whether the 2026.4.11 upgrade has run. The screens and jobs that use the
// engine keep working - without the SEO additions - on an installation where
// the files were deployed but the upgrade was not run yet, the same way the
// performance report defends against its missing tables. Probed once per
// request.
function pg_seo_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_seo_columns_exist('seo_flags');
    }

    return $ready;
}

// True when a column added by one of the SEO upgrades exists on all three
// record tables.
//
// Every SEO upgrade ALTERs page, products and product_groups in a loop and the
// version is written only after the whole function returns, so a request cut
// off between two of those ALTERs leaves the column on one table and not the
// others. Probing only `page` then answers yes and the Products screen names a
// column that is not there - which takes that screen down until someone runs
// the installer again. pg_perf_stats_has_entity() guards the same hazard on
// perf_stats for the same reason.
function pg_seo_columns_exist($column)
{
    static $cache = array();

    if (!isset($cache[$column])) {

        $cache[$column] = true;

        foreach (array('page', 'products', 'product_groups') as $table) {
            // The underscore is a LIKE wildcard, so an unescaped column name
            // would also match a column that merely resembles it. The two
            // sibling probes escape it; this one has to agree.
            if (!db_item("SHOW COLUMNS FROM `" . $table . "` LIKE '" . e(str_replace('_', '\\_', $column)) . "'")) {
                $cache[$column] = false;
                break;
            }
        }
    }

    return $cache[$column];
}

// Whether the 2026.4.12 upgrade has run, which is what the structure half
// needs. Probed separately from the meta half so an installation that ran
// one upgrade but not the other keeps working on the half it has.
function pg_seo_structure_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_seo_columns_exist('seo_struct_score');
    }

    return $ready;
}

// Whether the 2026.4.13 upgrade has run. seo_link_score arrives a version
// after seo_struct_score, so the structure probe cannot answer for it: an
// installation stopped between the two would have every SEO screen name a
// column that does not exist yet.
function pg_seo_link_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_seo_columns_exist('seo_link_score');
    }

    return $ready;
}

// Weight of each group in the composed score.
//
// The structure group only enters once the record has actually been
// analyzed. Before that the meta score stands alone at full weight - a
// record whose structure has never been examined is not a record with bad
// structure, and scoring it as though it were would make every score drop
// the day the feature was installed rather than the day something broke.
function pg_seo_compose($meta_score, $structure_score, $link_score = null, $speed_score = null)
{
    // The three cases that existed before the speed group are written exactly
    // as they were. Restating them as hundredths of a whole looks equivalent
    // and is not: 0.35 and 0.15 are not representable in binary, so a record
    // landing on an exact half rounds the other way and its stored score moves
    // by a point for no reason anyone could explain.
    if ($speed_score === null) {

        if ($structure_score === null) {
            return (int) $meta_score;
        }

        if ($link_score === null) {
            return (int) round(($meta_score * 0.6) + ($structure_score * 0.4));
        }

        return (int) round(($meta_score * 0.50) + ($structure_score * 0.35) + ($link_score * 0.15));
    }

    $weights = pg_seo_group_weights($structure_score, $link_score, $speed_score);

    $total = ($meta_score * $weights['meta'])
        + ((($structure_score === null) ? 0 : $structure_score) * $weights['structure'])
        + ((($link_score === null) ? 0 : $link_score) * $weights['links'])
        + ($speed_score * $weights['speed']);

    return (int) round($total / 100);
}

// Weight each group carries in the composed score, for the detail panel.
// pg_seo_compose() reads the same table, so the panel cannot drift from the
// arithmetic it is describing.
//
// A group that has not been measured is left out of the denominator rather
// than scored as zero. A record whose structure has never been analyzed is
// not a record with bad structure, and one nobody has visited is not a slow
// one; scoring either as though it were would make the number say something
// the software does not know.
//
// The speed column is the same 10 points wherever it appears, and the groups
// beside it keep their proportions to one another - each row below is the row
// above scaled to leave room for it.
function pg_seo_group_weights($structure_score, $link_score = null, $speed_score = null)
{
    $has_speed = ($speed_score !== null);

    if ($structure_score === null) {
        return $has_speed
            ? array('meta' => 90, 'structure' => 0, 'links' => 0, 'speed' => 10)
            : array('meta' => 100, 'structure' => 0, 'links' => 0, 'speed' => 0);
    }

    if ($link_score === null) {
        return $has_speed
            ? array('meta' => 54, 'structure' => 36, 'links' => 0, 'speed' => 10)
            : array('meta' => 60, 'structure' => 40, 'links' => 0, 'speed' => 0);
    }

    return $has_speed
        ? array('meta' => 45, 'structure' => 32, 'links' => 13, 'speed' => 10)
        : array('meta' => 50, 'structure' => 35, 'links' => 15, 'speed' => 0);
}

// Whether the 2026.4.15 upgrade has run, which is what the speed half needs.
// Probed separately from the other two so an installation part-way through
// its upgrades keeps scoring on the halves it does have.
function pg_seo_speed_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_seo_columns_exist('seo_speed_score');
    }

    return $ready;
}

// Whether there is anything to read the measurements from.
//
// The table is probed before its columns, and both are probed rather than
// assumed. seo_speed_score is added to the record tables unconditionally by
// the upgrade, so its presence says nothing about perf_stats - and an install
// that has dropped the performance table would otherwise take a fatal on
// every screen that scores anything, because db_item() turns a missing table
// into output_error().
function pg_seo_speed_source_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = db_item("SHOW TABLES LIKE 'perf_stats'")
            && (count(db_items("SHOW COLUMNS FROM perf_stats LIKE 'entity\\_%'")) >= 2);
    }

    return $ready;
}

// Days of measurements the speed group is computed from.
function pg_seo_speed_window_days()
{
    return defined('SEO_SPEED_WINDOW_DAYS') ? (int) SEO_SPEED_WINDOW_DAYS : 30;
}

// Requests a record needs before its speed is judged at all.
//
// Without a floor this measures noise. A page visited three times, one of
// which happened during a backup, has a slow-request ratio of 33% and would
// be marked as a problem on the strength of a single measurement. The
// threshold reads the recorded hit count, not an estimate of real traffic:
// where PERF_MONITOR_SAMPLE_RATE is below 100 the recorded number is already
// the sample, and it is the sample that decides how much can be concluded.
function pg_seo_speed_minimum_hits()
{
    return defined('SEO_SPEED_MINIMUM_HITS') ? (int) SEO_SPEED_MINIMUM_HITS : 30;
}

// Measured server timings per record, from the buckets the performance
// monitor has been filling on every request.
//
// One grouped read per scoring run, narrowed to named records when the caller
// has them - the same shape as pg_seo_structure_flag_map().
//
// Front-end only: a back-end screen is not a scored record. Rows written
// before the upgrade carry entity_id 0 and are excluded by it.
function pg_seo_speed_map($type, $ids = null)
{
    if (!pg_seo_speed_schema_ready() || !pg_seo_speed_source_ready()) {
        return array();
    }

    $sql_ids = '';

    if (is_array($ids) && $ids) {
        $sql_ids = " AND entity_id IN (" . implode(',', array_map('intval', $ids)) . ")";
    }

    $window_start = time() - (pg_seo_speed_window_days() * 86400);
    $map = array();

    $rows = db_items(
        "SELECT
            entity_id,
            SUM(hits) AS hits,
            SUM(slow_hits) AS slow_hits,
            SUM(total_ms) AS total_ms,
            SUM(total_kb) AS total_kb
        FROM perf_stats
        WHERE
            (area = 'frontend')
            AND (entity_type = '" . e($type) . "')
            AND (entity_id > 0)
            AND (hour_start >= '" . (int) $window_start . "')" . $sql_ids . "
        GROUP BY entity_id");

    foreach ($rows as $row) {

        $hits = (int) $row['hits'];

        if ($hits < 1) {
            continue;
        }

        $map[(int) $row['entity_id']] = array(
            'hits'       => $hits,
            'slow_hits'  => (int) $row['slow_hits'],
            'average_ms' => (int) round(((float) $row['total_ms']) / $hits),
            'average_kb' => (int) round(((float) $row['total_kb']) / $hits),
        );
    }

    return $map;
}

// Score the server side of how this record is delivered, out of 100.
//
// Returns a null score when there is not enough traffic to say anything, and
// the caller then leaves the whole group out of the denominator.
//
// Response time is not itself a ranking factor, but it is the floor under
// Largest Contentful Paint: a server taking 1.8 seconds to answer cannot have
// a good LCP no matter what the page does afterwards. 800 ms is the ceiling
// recommended for a good LCP, 400 ms is comfortable, and past 1500 ms the
// server alone has decided the outcome.
//
// Peak memory is not an SEO signal at all. It is here because it is the early
// warning for the one that is: a catalogue page answering in 300 ms on an idle
// server while reserving 200 MB per request is the first thing to fall over
// when traffic arrives, and this table already knows the number.
function pg_seo_evaluate_speed($stats)
{
    $defs = pg_seo_flag_defs();

    $result = array('score' => null, 'checks' => array(), 'flags' => 0, 'hits' => 0);

    if (!is_array($stats)) {
        return $result;
    }

    $hits = (int) $stats['hits'];
    $result['hits'] = $hits;

    if ($hits < pg_seo_speed_minimum_hits()) {
        return $result;
    }

    $average_ms = (int) $stats['average_ms'];
    $slow_ratio = ((int) $stats['slow_hits']) / $hits;
    $average_kb = (int) $stats['average_kb'];

    $earned = 0;

    // D1 - average response time.
    if ($average_ms <= 400) {
        $earned += 50;
        $status = 'ok';
    } elseif ($average_ms <= 800) {
        $earned += 35;
        $status = 'ok';
    } elseif ($average_ms <= 1500) {
        $earned += 20;
        $status = 'warn';
    } else {
        $status = 'fail';
    }

    if ($average_ms > 800) {
        $result['flags'] |= $defs['speed_slow'];
    }

    $result['checks'][] = array(
        'c' => 'speed_response', 'w' => 50, 'e' => $earned, 's' => $status,
        'd' => array('ms' => $average_ms, 'hits' => $hits));

    // D2 - how often a request crosses the slow threshold. An average hides
    // a page that is usually fine and occasionally unusable, which is the
    // shape a visitor actually remembers.
    $before = $earned;

    if ($slow_ratio <= 0.01) {
        $earned += 30;
        $status = 'ok';
    } elseif ($slow_ratio <= 0.05) {
        $earned += 20;
        $status = 'ok';
    } elseif ($slow_ratio <= 0.15) {
        $earned += 10;
        $status = 'warn';
    } else {
        $status = 'fail';
    }

    // The threshold travels with the finding. What counts as slow is
    // PERF_MONITOR_SLOW_MS, an operator-tunable define whose usual reason for
    // being changed is the performance report, not this score - so a site that
    // lowered it to catch more outliers there would otherwise see every page
    // lose points here with nothing on screen connecting the two.
    $result['checks'][] = array(
        'c' => 'speed_slow_ratio', 'w' => 30, 'e' => ($earned - $before), 's' => $status,
        'd' => array(
            'percent' => round($slow_ratio * 100, 1),
            'slow' => (int) $stats['slow_hits'],
            'hits' => $hits,
            'threshold' => (defined('PERF_MONITOR_SLOW_MS') ? (int) PERF_MONITOR_SLOW_MS : 1000)));

    // D3 - memory per request, averaged like the two checks above it.
    //
    // The stored high-water mark would be the obvious column to read and is
    // the wrong one: it never decays, so a single heavy render - an export, a
    // crawler on a filtered catalogue - pins the record at the bottom of this
    // check for the whole window on the strength of one request.
    $before = $earned;

    if ($average_kb <= 32768) {
        $earned += 20;
        $status = 'ok';
    } elseif ($average_kb <= 98304) {
        $earned += 10;
        $status = 'warn';
    } else {
        $status = 'fail';
    }

    $result['checks'][] = array(
        'c' => 'speed_memory', 'w' => 20, 'e' => ($earned - $before), 's' => $status,
        'd' => array('kb' => $average_kb));

    $result['score'] = (int) $earned;

    return $result;
}

// Page types the sitemap generator accepts. Mirrors the WHERE list in
// get_sitemap_info.php; a type outside this list can never appear in
// sitemap.xml, so its indexability check does not apply.
function pg_seo_sitemap_eligible_types()
{
    return array(
        'standard', 'folder view', 'photo gallery', 'custom form',
        'form list view', 'form view directory', 'calendar view', 'catalog',
        'express order', 'order form', 'shopping cart', 'search results',
    );
}

// Page types whose title and description are overridden from content at
// render time (get_page_content.php): products for catalog pages, submitted
// form data for form item views. Their own empty meta fields are not a
// defect, so the title and description checks do not apply to them.
function pg_seo_inherited_types()
{
    return array('catalog', 'catalog detail', 'form item view');
}

// Normalization key for duplicate detection. Case and surrounding whitespace
// are ignored because "Hakkımızda " and "hakkımızda" are the same title to a
// search engine.
//
// fold_keyword_for_duplicates() rather than mb_strtolower() alone. Unicode's
// default lowercasing of "İ" leaves a combining dot behind, so "İSTANBUL" and
// "İstanbul" produce different keys and two pages sharing a title are never
// reported. The comparison used to be done in SQL, where utf8mb4_unicode_ci
// folded all four forms of the letter; moving it into PHP quietly lost that.
function pg_seo_dup_key($value)
{
    return md5(pg_seo_fold_text($value));
}

// Case fold for comparing two pieces of site text. Shares the keyword fold so
// that "the same words" means one thing across the whole feature.
function pg_seo_fold_text($value)
{
    if (function_exists('fold_keyword_for_duplicates')) {
        return fold_keyword_for_duplicates($value);
    }

    return mb_strtolower(trim((string) $value), 'UTF-8');
}

// Map a raw table row onto the field names the evaluator understands, so the
// scoring rules are written once for all three record types.
function pg_seo_normalize_entity($type, $row)
{
    if ($type == 'page') {
        return array(
            'type'            => 'page',
            'id'              => (int) $row['page_id'],
            'name'            => (string) ($row['page_name'] ?? ''),
            'slug'            => (string) ($row['page_name'] ?? ''),
            'title'           => trim((string) ($row['page_title'] ?? '')),
            'description'     => trim((string) ($row['page_meta_description'] ?? '')),
            'keywords'        => (string) ($row['page_search_keywords'] ?? ''),
            'searchable'      => ((string) ($row['page_search'] ?? '')) === '1',
            'page_type'       => (string) ($row['page_type'] ?? 'standard'),
            'sitemap'         => ((string) ($row['sitemap'] ?? '')) === '1',
            'folder_public'   => !empty($row['folder_public']),
            'folder_archived' => !empty($row['folder_archived']),
        );
    }

    // products and product_groups share their field names.
    $entity = array(
        'type'              => $type,
        'id'                => (int) $row['id'],
        'name'              => (string) ($row['name'] ?? ''),
        'slug'              => (string) ($row['address_name'] ?? ''),
        'title'             => trim((string) ($row['title'] ?? '')),
        'description'       => trim((string) ($row['meta_description'] ?? '')),
        'keywords'          => (string) ($row['keywords'] ?? ''),
        'searchable'        => ((string) ($row['enabled'] ?? '')) === '1',
        'short_description' => (string) ($row['short_description'] ?? ''),
        'full_description'  => (string) ($row['full_description'] ?? ''),
        'image_name'        => (string) ($row['image_name'] ?? ''),
    );

    if ($type == 'product') {
        $entity['brand'] = trim((string) ($row['brand'] ?? ''));
        $entity['gtin']  = trim((string) ($row['gtin'] ?? ''));
        $entity['mpn']   = trim((string) ($row['mpn'] ?? ''));
    }

    return $entity;
}

// Count comma separated keyword terms the way the tagin fields store them.
function pg_seo_count_terms($keywords)
{
    $count = 0;

    foreach (explode(',', (string) $keywords) as $term) {
        if (trim($term) !== '') {
            $count++;
        }
    }

    return $count;
}

// Word count of an HTML fragment, for the product description volume check.
function pg_seo_word_count($html)
{
    $text = strip_tags((string) $html);
    $normalized = preg_replace('/\s+/u', ' ', $text);

    // The /u modifier makes preg_replace return NULL on invalid UTF-8, and a
    // single stray byte in an imported description is enough. Falling back to
    // the byte-wise pattern keeps the count honest instead of scoring a fully
    // written product as having no words at all.
    if ($normalized === NULL) {
        $normalized = preg_replace('/\s+/', ' ', $text);
    }

    $normalized = trim((string) $normalized);

    if ($normalized === '') {
        return 0;
    }

    return count(explode(' ', $normalized));
}

// Evaluate one record. Pure: reads its arguments, runs no queries, writes
// nothing. $context carries the site-wide state that cannot be derived from
// a single row (duplicate maps, the account wide title).
//
// Returns array('score' => int|null, 'flags' => int, 'analysis' => array).
function pg_seo_evaluate_meta($entity, $context)
{
    $defs = pg_seo_flag_defs();
    $flags = 0;
    $checks = array();
    $inherited = ($entity['type'] == 'page')
        && in_array($entity['page_type'], pg_seo_inherited_types());

    $title = $entity['title'];
    $description = $entity['description'];
    $title_len = mb_strlen($title, 'UTF-8');
    $description_len = mb_strlen($description, 'UTF-8');

    // ---- Title -------------------------------------------------------------

    if (!$inherited) {
        if ($title === '') {
            $flags |= $defs['title_missing'];
            $checks[] = array('c' => 'title_present', 'w' => 20, 'e' => 0, 's' => 'fail');
        } else {
            $checks[] = array('c' => 'title_present', 'w' => 20, 'e' => 20, 's' => 'ok');
        }

        // The length, uniqueness and filler checks only apply when a title
        // exists. An empty title is one 20 point failure, not four failures:
        // stacking derived penalties on the same root cause would overstate it.
        if ($title !== '') {
            if (($title_len >= 50) && ($title_len <= 60)) {
                $earned = 10;
                $status = 'ok';
            } elseif ((($title_len >= 30) && ($title_len <= 49)) || (($title_len >= 61) && ($title_len <= 70))) {
                $earned = 6;
                $status = 'warn';
            } elseif ((($title_len >= 10) && ($title_len <= 29)) || (($title_len >= 71) && ($title_len <= 80))) {
                $earned = 3;
                $status = 'warn';
            } else {
                $earned = 0;
                $status = 'fail';
            }

            if ($title_len < 30) {
                $flags |= $defs['title_short'];
            } elseif ($title_len > 70) {
                $flags |= $defs['title_long'];
            }

            $checks[] = array('c' => 'title_length', 'w' => 10, 'e' => $earned, 's' => $status, 'd' => array('len' => $title_len));

            $copies = isset($context['duplicate_titles'][pg_seo_dup_key($title)])
                ? $context['duplicate_titles'][pg_seo_dup_key($title)]
                : 1;

            if ($copies <= 1) {
                $checks[] = array('c' => 'title_unique', 'w' => 8, 'e' => 8, 's' => 'ok');
            } elseif ($copies == 2) {
                $flags |= $defs['title_duplicate'];
                $checks[] = array('c' => 'title_unique', 'w' => 8, 'e' => 4, 's' => 'warn', 'd' => array('copies' => $copies));
            } else {
                $flags |= $defs['title_duplicate'];
                $checks[] = array('c' => 'title_unique', 'w' => 8, 'e' => 0, 's' => 'fail', 'd' => array('copies' => $copies));
            }

            // A title that just repeats the record name or the account wide
            // title carries no information of its own.
            $normalized_title = pg_seo_fold_text($title);
            $is_filler = ($normalized_title === pg_seo_fold_text($entity['name']))
                || (($context['site_title'] !== '') && ($normalized_title === pg_seo_fold_text($context['site_title'])));

            if ($is_filler) {
                $flags |= $defs['title_filler'];
                $checks[] = array('c' => 'title_distinct', 'w' => 4, 'e' => 0, 's' => 'fail');
            } else {
                $checks[] = array('c' => 'title_distinct', 'w' => 4, 'e' => 4, 's' => 'ok');
            }
        }
    }

    // ---- Description -------------------------------------------------------

    if (!$inherited) {
        if ($description === '') {
            $flags |= $defs['desc_missing'];
            $checks[] = array('c' => 'desc_present', 'w' => 18, 'e' => 0, 's' => 'fail');
        } else {
            $checks[] = array('c' => 'desc_present', 'w' => 18, 'e' => 18, 's' => 'ok');

            if (($description_len >= 150) && ($description_len <= 160)) {
                $earned = 10;
                $status = 'ok';
            } elseif ((($description_len >= 120) && ($description_len <= 149)) || (($description_len >= 161) && ($description_len <= 170))) {
                $earned = 6;
                $status = 'warn';
            } elseif ((($description_len >= 70) && ($description_len <= 119)) || (($description_len >= 171) && ($description_len <= 200))) {
                $earned = 3;
                $status = 'warn';
            } else {
                $earned = 0;
                $status = 'fail';
            }

            if ($description_len < 120) {
                $flags |= $defs['desc_short'];
            } elseif ($description_len > 170) {
                $flags |= $defs['desc_long'];
            }

            $checks[] = array('c' => 'desc_length', 'w' => 10, 'e' => $earned, 's' => $status, 'd' => array('len' => $description_len));

            $copies = isset($context['duplicate_descriptions'][pg_seo_dup_key($description)])
                ? $context['duplicate_descriptions'][pg_seo_dup_key($description)]
                : 1;

            if ($copies <= 1) {
                $checks[] = array('c' => 'desc_unique', 'w' => 7, 'e' => 7, 's' => 'ok');
            } elseif ($copies == 2) {
                $flags |= $defs['desc_duplicate'];
                $checks[] = array('c' => 'desc_unique', 'w' => 7, 'e' => 3, 's' => 'warn', 'd' => array('copies' => $copies));
            } else {
                $flags |= $defs['desc_duplicate'];
                $checks[] = array('c' => 'desc_unique', 'w' => 7, 'e' => 0, 's' => 'fail', 'd' => array('copies' => $copies));
            }
        }
    }

    // ---- Source health (inherited pages) -----------------------------------

    // A catalog page is only as healthy as the products it shows: its own
    // title and description checks left the denominator above, and this one
    // takes their weight. A form item view is a configuration check instead -
    // one missing field marking gives every generated item page the same
    // account-wide title, so it is a single switch with site-wide blast
    // radius.
    if ($inherited) {
        $source_resolved = false;

        if ($entity['page_type'] == 'form item view') {
            if (isset($context['form_item_view_form'][$entity['id']])) {
                $source_resolved = true;
                $form_page_id = $context['form_item_view_form'][$entity['id']];
                $fields = $context['form_rss_fields'][$form_page_id]
                    ?? array('title' => false, 'description' => false);

                if ($fields['title'] && $fields['description']) {
                    $checks[] = array('c' => 'source_form_fields', 'w' => 55, 'e' => 55, 's' => 'ok');
                } elseif ($fields['title']) {
                    $checks[] = array('c' => 'source_form_fields', 'w' => 55, 'e' => 33, 's' => 'warn', 'd' => array('title' => true, 'description' => false));
                } else {
                    $flags |= $defs['source_thin'];
                    $checks[] = array('c' => 'source_form_fields', 'w' => 55, 'e' => 0, 's' => 'fail', 'd' => array('title' => false, 'description' => false));
                }
            }
        } else {
            $group_id = 0;

            if ($entity['page_type'] == 'catalog') {
                $group_id = $context['catalog_page_group'][$entity['id']] ?? 0;
            } elseif ($entity['page_type'] == 'catalog detail') {
                $group_id = $context['catalog_detail_group'][$entity['id']] ?? 0;
            }

            $stats = $group_id ? pg_seo_group_product_stats($group_id) : null;

            if ($stats !== null) {
                $source_resolved = true;

                if ($stats['products'] == 0) {
                    $flags |= $defs['source_thin'];
                    $checks[] = array('c' => 'source_products', 'w' => 55, 'e' => 0, 's' => 'fail', 'd' => array('reason' => 'no_products'));

                // This check carries the weight the page's own title and
                // description checks gave up, so it is the only thing with any
                // real weight behind an inherited page - what is left is a slug
                // check and an indexability check that an untouched page
                // passes. It has to say something on every pass, or a catalog
                // page with an empty title is written at a confident 100.
                } else {
                    $earned = (int) round(55 * $stats['health'] / 100);
                    $status = ($stats['health'] >= 80) ? 'ok' : (($stats['health'] >= 55) ? 'warn' : 'fail');

                    if ((($stats['thin'] + $stats['partial']) / $stats['products']) > 0.3) {
                        $flags |= $defs['source_thin'];
                    }

                    $checks[] = array(
                        'c' => 'source_products', 'w' => 55, 'e' => $earned, 's' => $status,
                        'd' => array(
                            'health' => $stats['health'],
                            'products' => $stats['products'],
                            'complete' => $stats['complete'],
                            'thin' => ($stats['thin'] + $stats['partial'])),
                    );
                }
            }
        }

        // An inherited page whose source cannot be resolved is the worst of
        // both worlds: its own title and description checks were skipped
        // because something else was supposed to supply them, and nothing
        // does. Without this the page scores on keywords and its name alone
        // and comes out near perfect while rendering with no title at all.
        if (!$source_resolved) {
            $flags |= $defs['source_thin'];
            $checks[] = array('c' => 'source_unresolved', 'w' => 55, 'e' => 0, 's' => 'fail');
        }
    }

    // ---- Keywords ----------------------------------------------------------

    // What is scored is the promote-on-keyword field (page_search_keywords on
    // pages, keywords on products and groups): the terms the operator chose on
    // purpose. They promote the record in the built-in site search and they are
    // what the tag cloud is rendered from, so they are the operator's own
    // navigation surface, not a signal aimed at an external engine.
    //
    // Two records are left out. One that is not in the site search, because the
    // field does nothing there. And a page whose content is inherited from a
    // catalog or a form, because a single keyword list on such a page would have
    // to stand for every item rendered through it.
    if ($entity['searchable'] && !$inherited) {
        $terms = pg_seo_count_terms($entity['keywords']);

        if ($terms == 0) {
            $flags |= $defs['keywords_missing'];
            $checks[] = array('c' => 'keywords', 'w' => 13, 'e' => 0, 's' => 'fail', 'd' => array('terms' => 0));
        } elseif ($terms <= 2) {
            $flags |= $defs['keywords_thin'];
            $checks[] = array('c' => 'keywords', 'w' => 13, 'e' => 5, 's' => 'warn', 'd' => array('terms' => $terms));
        } elseif ($terms <= 8) {
            $checks[] = array('c' => 'keywords', 'w' => 13, 'e' => 13, 's' => 'ok', 'd' => array('terms' => $terms));
        } else {
            $flags |= $defs['keywords_stuffed'];
            $checks[] = array('c' => 'keywords', 'w' => 13, 'e' => 8, 's' => 'warn', 'd' => array('terms' => $terms));
        }
    }

    // ---- Address / slug ----------------------------------------------------

    if ($entity['type'] == 'page') {
        // Non-ASCII characters in a name are not a style issue here: IIS
        // re-encodes the decoded path into the server ANSI codepage, so such
        // a page cannot be reached by its own URL at all.
        $slug = $entity['slug'];
        $earned = 5;
        $problems = array();

        if (preg_match('/[^\x20-\x7E]/', $slug)) {
            $flags |= $defs['url_non_ascii'];
            $earned--;
            $problems[] = 'non_ascii';
        }

        if (mb_strpos($slug, ' ') !== false) {
            $earned--;
            $problems[] = 'space';
        }

        if (mb_strpos($slug, '_') !== false) {
            $earned--;
            $problems[] = 'underscore';
        }

        if (mb_strlen($slug, 'UTF-8') > 60) {
            $earned--;
            $problems[] = 'long';
        }

        $earned = max(0, $earned);
        $status = ($earned == 5) ? 'ok' : (($earned >= 3) ? 'warn' : 'fail');
        $check = array('c' => 'slug', 'w' => 5, 'e' => $earned, 's' => $status);

        if ($problems) {
            $check['d'] = array('problems' => $problems);
        }

        $checks[] = $check;
    } else {
        // Product and group addresses are usually generated; an empty one is
        // only a soft finding because the item name is used instead.
        $slug = $entity['slug'];

        if ($slug === '') {
            $checks[] = array('c' => 'slug', 'w' => 5, 'e' => 2, 's' => 'warn', 'd' => array('problems' => array('empty')));
        } elseif (preg_match('/[^\x20-\x7E]/', $slug)) {
            $flags |= $defs['url_non_ascii'];
            $checks[] = array('c' => 'slug', 'w' => 5, 'e' => 0, 's' => 'fail', 'd' => array('problems' => array('non_ascii')));
        } else {
            $checks[] = array('c' => 'slug', 'w' => 5, 'e' => 5, 's' => 'ok');
        }
    }

    // ---- Indexability (pages) ----------------------------------------------

    // Applies only when the page type can appear in the sitemap at all and
    // the operator asked for it. A page kept out of the sitemap on purpose is
    // not a finding. A page marked for the sitemap that the generator will
    // skip anyway (private folder, archived folder) is the silent kind of
    // problem this whole feature exists to surface.
    if (($entity['type'] == 'page')
        && $entity['sitemap']
        && in_array($entity['page_type'], pg_seo_sitemap_eligible_types())
    ) {
        if ($entity['folder_public'] && !$entity['folder_archived']) {
            $checks[] = array('c' => 'indexable', 'w' => 5, 'e' => 5, 's' => 'ok');
        } else {
            $flags |= $defs['sitemap_blocked'];
            $checks[] = array(
                'c' => 'indexable', 'w' => 5, 'e' => 0, 's' => 'fail',
                'd' => array('reason' => $entity['folder_archived'] ? 'archived' : 'not_public'),
            );
        }
    }

    // ---- Product specific checks -------------------------------------------

    if (($entity['type'] == 'product') || ($entity['type'] == 'product_group')) {
        if (trim($entity['short_description']) === '') {
            $checks[] = array('c' => 'short_description', 'w' => 6, 'e' => 0, 's' => 'fail');
        } else {
            $checks[] = array('c' => 'short_description', 'w' => 6, 'e' => 6, 's' => 'ok');
        }

        $words = pg_seo_word_count($entity['full_description']);

        if ($words >= 150) {
            $checks[] = array('c' => 'description_volume', 'w' => 8, 'e' => 8, 's' => 'ok', 'd' => array('words' => $words));
        } elseif ($words >= 50) {
            $checks[] = array('c' => 'description_volume', 'w' => 8, 'e' => 4, 's' => 'warn', 'd' => array('words' => $words));
        } else {
            $checks[] = array('c' => 'description_volume', 'w' => 8, 'e' => 0, 's' => 'fail', 'd' => array('words' => $words));
        }

        if (trim($entity['image_name']) === '') {
            $checks[] = array('c' => 'image', 'w' => 6, 'e' => 0, 's' => 'fail');
        } else {
            $checks[] = array('c' => 'image', 'w' => 6, 'e' => 6, 's' => 'ok');
        }
    }

    if ($entity['type'] == 'product') {
        // brand / gtin / mpn feed the product structured data and shopping
        // feeds; each filled field earns a share.
        $filled = 0;

        foreach (array('brand', 'gtin', 'mpn') as $field) {
            if ($entity[$field] !== '') {
                $filled++;
            }
        }

        $earned_map = array(0 => 0, 1 => 3, 2 => 5, 3 => 8);
        $checks[] = array(
            'c' => 'structured_fields', 'w' => 8, 'e' => $earned_map[$filled],
            's' => ($filled == 3) ? 'ok' : (($filled > 0) ? 'warn' : 'fail'),
            'd' => array('filled' => $filled),
        );
    }

    // ---- Normalize ---------------------------------------------------------

    $applicable = 0;
    $earned_total = 0;

    foreach ($checks as $check) {
        $applicable += $check['w'];
        $earned_total += $check['e'];
    }

    $score = ($applicable > 0) ? (int) round($earned_total / $applicable * 100) : null;

    $analysis = array(
        'v'         => 1,
        'score'     => $score,
        'groups'    => array('meta' => $score),
        'inherited' => $inherited,
        'earned'    => $earned_total,
        'applicable'=> $applicable,
        'checks'    => $checks,
        'at'        => time(),
    );

    return array('score' => $score, 'flags' => $flags, 'analysis' => $analysis);
}

// SEO health of the products a catalog page shows, for the group it is bound
// to. Returns products / scored / avg / weak, or NULL when the group has no
// entry at all.
//
// Built on first use and kept for the rest of the request. It reads every
// product row, so computing it up front - as the context builder used to -
// meant a full products scan on every list render and on every detail panel
// opened, including the overwhelming majority of pages that inherit nothing
// and never ask for it.
function pg_seo_group_product_stats($group_id)
{
    static $stats = null;

    $group_id = (int) $group_id;

    if ($stats === null) {
        $stats = array();

        // Walk the group tree in memory, the same way the sitemap generator
        // resolves a catalog page's scope: a group means the group and
        // everything under it.
        $group_children = array();

        foreach (db_items("SELECT id, parent_id FROM product_groups WHERE enabled = '1'") as $group_row) {
            $group_children[(int) $group_row['parent_id']][] = (int) $group_row['id'];
        }

        $group_products = array();

        foreach (db_items("SELECT product, product_group FROM products_groups_xref") as $xref_row) {
            $group_products[(int) $xref_row['product_group']][] = (int) $xref_row['product'];
        }

        // The products' own title and description, not their scores.
        //
        // A catalog page inherits exactly those two fields from the product it
        // shows, so they are what "how healthy is the source" means here - a
        // more direct measure than an average of composite scores, and one
        // that does not depend on whether the product pass has run yet.
        // Reading the scores made this page's result a function of the order
        // the passes happened to run in, which is not a property of the page.
        $product_scores = array();

        foreach (db_items("SELECT id, enabled, title, meta_description FROM products") as $product_row) {
            $product_scores[(int) $product_row['id']] = $product_row;
        }

        foreach (array_keys($group_children) as $parent_id) {
            foreach ($group_children[$parent_id] as $child_id) {
                $pending = array($child_id);
                $seen_groups = array();
                $product_ids = array();

                while ($pending) {
                    $current = array_pop($pending);

                    if (isset($seen_groups[$current])) {
                        continue;
                    }

                    $seen_groups[$current] = true;

                    foreach (($group_products[$current] ?? array()) as $product_id) {
                        $product_ids[$product_id] = true;
                    }

                    foreach (($group_children[$current] ?? array()) as $descendant_id) {
                        $pending[] = $descendant_id;
                    }
                }

                $total = 0;
                $complete = 0;
                $partial = 0;

                foreach (array_keys($product_ids) as $product_id) {
                    $product = $product_scores[$product_id] ?? null;

                    if (!$product || (((string) $product['enabled']) !== '1')) {
                        continue;
                    }

                    $total++;

                    $has_title = (trim((string) $product['title']) !== '');
                    $has_description = (trim((string) $product['meta_description']) !== '');

                    if ($has_title && $has_description) {
                        $complete++;
                    } elseif ($has_title || $has_description) {
                        $partial++;
                    }
                }

                // A product with both fields counts whole, one with a single
                // field counts half: the catalog page inherits whichever is
                // there, so half the source is genuinely half the problem.
                $stats[$child_id] = array(
                    'products' => $total,
                    'complete' => $complete,
                    'partial' => $partial,
                    'thin' => ($total - $complete - $partial),
                    'health' => $total ? (int) round(((($complete + ($partial / 2)) / $total) * 100)) : 0,
                );
            }
        }
    }

    return isset($stats[$group_id]) ? $stats[$group_id] : null;
}

// Structure findings folded into seo_flags bits, keyed by record id.
//
// The findings themselves live in seo_issue because there can be any number
// of them per record. The bits exist so the list screens can filter on one
// of them without joining that table for every row, and they are set here
// rather than by the analysis job because seo_flags is written by the meta
// pass - having both passes write the same column would mean each one
// erasing the other's bits.
//
// One grouped query for the whole table: the alternative, a lookup per
// record, would be thousands of queries on a full pass.
function pg_seo_structure_flag_map($type, $ids = null)
{
    if (!pg_seo_structure_schema_ready()) {
        return array();
    }

    $defs = pg_seo_flag_defs();
    $map = array();
    $sql_ids = '';

    // Scoring an explicit handful of records - the detail panel, the lazy
    // catch-up on a list screen - has no use for the whole table.
    if (is_array($ids) && $ids) {
        $sql_ids = " AND entity_id IN (" . implode(',', array_map('intval', $ids)) . ")";
    }

    $rows = db_items(
        "SELECT
            entity_id,
            MAX(severity = 'error') AS has_error,
            MAX(severity = 'warning') AS has_warning,
            MAX(code = 'h1_missing') AS no_h1,
            MAX(code = 'h1_multiple') AS multi_h1,
            MAX(code = 'img_no_alt') AS img_no_alt,
            MAX(code = 'thin_content') AS thin_content,
            MAX(code = 'link_broken_internal') AS broken_internal_link,
            MAX(code = 'orphan_page') AS orphan
        FROM seo_issue
        WHERE entity_type = '" . e($type) . "'" . $sql_ids . "
        GROUP BY entity_id");

    foreach ($rows as $row) {
        $flags = 0;

        if ($row['has_error']) {
            $flags |= $defs['struct_error'];
        }

        if ($row['has_warning']) {
            $flags |= $defs['struct_warning'];
        }

        if ($row['no_h1']) {
            $flags |= $defs['no_h1'];
        }

        if ($row['multi_h1']) {
            $flags |= $defs['multi_h1'];
        }

        if ($row['img_no_alt']) {
            $flags |= $defs['img_no_alt'];
        }

        if ($row['thin_content']) {
            $flags |= $defs['thin_content'];
        }

        if ($row['broken_internal_link']) {
            $flags |= $defs['broken_internal_link'];
        }

        if ($row['orphan']) {
            $flags |= $defs['orphan'];
        }

        $map[(int) $row['entity_id']] = $flags;
    }

    return $map;
}

// Site-wide inputs the evaluator needs but a single row cannot provide.
// Built once per recalculation run, in PHP rather than SQL so the duplicate
// keys use exactly the same normalization (pg_seo_dup_key) as the evaluator;
// MySQL LOWER() disagrees with PHP on Turkish dotted/dotless I.
function pg_seo_build_context($type, $ids = null)
{
    $context = array(
        'site_title'             => defined('TITLE') ? (string) TITLE : '',
        'duplicate_titles'       => array(),
        'duplicate_descriptions' => array(),
        'structure_flags'        => pg_seo_structure_flag_map($type, $ids),
        'speed'                  => pg_seo_speed_map($type, $ids),
    );

    if ($type == 'page') {
        // Pages whose title and description are inherited from content are
        // judged on the health of that content instead. The maps below let
        // the evaluator answer "how healthy is the source" without a query:
        //
        //   catalog        -> the linked product group's products
        //   catalog detail -> the group of the catalog page that points here
        //   form item view -> whether the bound custom form marks a title
        //                     and a description field for its items
        $context['catalog_page_group'] = array();
        $context['catalog_detail_group'] = array();
        $context['form_item_view_form'] = array();
        $context['form_rss_fields'] = array();

        foreach (db_items("SELECT page_id, product_group_id, catalog_detail_page_id FROM catalog_pages") as $catalog_page) {
            $context['catalog_page_group'][(int) $catalog_page['page_id']] = (int) $catalog_page['product_group_id'];

            if ((int) $catalog_page['catalog_detail_page_id']) {
                $context['catalog_detail_group'][(int) $catalog_page['catalog_detail_page_id']] = (int) $catalog_page['product_group_id'];
            }
        }

        foreach (db_items("SELECT page_id, custom_form_page_id FROM form_item_view_pages WHERE collection = 'a'") as $form_item_view_page) {
            $context['form_item_view_form'][(int) $form_item_view_page['page_id']] = (int) $form_item_view_page['custom_form_page_id'];
        }

        if ($context['form_item_view_form']) {
            foreach (db_items(
                "SELECT
                    page_id,
                    SUM(rss_field = 'title') AS has_title,
                    SUM(rss_field = 'description') AS has_description
                FROM form_fields
                GROUP BY page_id") as $form_field_row
            ) {
                $context['form_rss_fields'][(int) $form_field_row['page_id']] = array(
                    'title' => ((int) $form_field_row['has_title']) > 0,
                    'description' => ((int) $form_field_row['has_description']) > 0,
                );
            }
        }

        $query = "SELECT page_title AS title, page_meta_description AS description FROM page";
    } elseif ($type == 'product') {
        $query = "SELECT title, meta_description AS description FROM products";
    } else {
        $query = "SELECT title, meta_description AS description FROM product_groups";
    }

    $title_counts = array();
    $description_counts = array();

    foreach (db_items($query) as $row) {
        if (trim((string) $row['title']) !== '') {
            $key = pg_seo_dup_key($row['title']);
            $title_counts[$key] = isset($title_counts[$key]) ? $title_counts[$key] + 1 : 1;
        }

        if (trim((string) $row['description']) !== '') {
            $key = pg_seo_dup_key($row['description']);
            $description_counts[$key] = isset($description_counts[$key]) ? $description_counts[$key] + 1 : 1;
        }
    }

    // Only the duplicates are kept; unique values would bloat the map for no
    // reader. Empty values never enter the counts at all — they are already
    // penalized as missing, and counting them as duplicates of one another
    // would punish the same defect twice.
    foreach ($title_counts as $key => $count) {
        if ($count > 1) {
            $context['duplicate_titles'][$key] = $count;
        }
    }

    foreach ($description_counts as $key => $count) {
        if ($count > 1) {
            $context['duplicate_descriptions'][$key] = $count;
        }
    }

    return $context;
}

// Whether the impact figure can be computed at all.
//
// VISITOR_TRACKING as well as the table. The rollup table is created by its
// own upgrade whether or not the operator wants visitor statistics, and the
// only thing the switch turns off is the writer - so with tracking off the
// table sits there empty and every record would compute
// (100 - score) x log10(1 + 0) = 0. Zero reads as "nothing to fix", which is
// the opposite of "there are no figures to weigh this against".
//
// pg_seo_schema_ready() because the expression names seo_checked_at, which is
// one of the columns the SEO upgrade adds.
function pg_seo_traffic_ready()
{
    return (defined('VISITOR_TRACKING') && (VISITOR_TRACKING == true))
        && function_exists('pg_visitor_rollup_ready')
        && pg_visitor_rollup_ready()
        && pg_seo_schema_ready();
}

// Days of traffic the impact figure is weighed over.
function pg_seo_impact_window_days()
{
    return defined('SEO_IMPACT_WINDOW_DAYS') ? (int) SEO_IMPACT_WINDOW_DAYS : 30;
}

// Recent view counts alongside each record, as a LEFT JOIN the list screens
// can drop into their existing query.
//
// The visitor rollup is the source rather than perf_stats.hits, for three
// reasons: it is already grouped by record, it is never sampled, and it has
// no exclusions - perf_stats deliberately leaves api.php out and folds every
// failure into one bucket, both of which are right for a performance report
// and wrong for "how many people saw this".
//
// Returns an empty string when there is no traffic to read, and the caller
// then has no impact column rather than a broken query.
function pg_seo_traffic_join($type, $id_expression)
{
    if (!pg_seo_traffic_ready()) {
        return '';
    }

    $since = date('Y-m-d', time() - (pg_seo_impact_window_days() * 86400));

    if ($type == 'page') {
        return
            "LEFT JOIN (
                SELECT page_id AS entity_id, SUM(views) AS views
                FROM visitor_content_hourly
                WHERE (stat_date >= '" . e($since) . "') AND (page_id > 0)
                GROUP BY page_id
            ) AS seo_traffic ON seo_traffic.entity_id = " . $id_expression;
    }

    return
        "LEFT JOIN (
            SELECT item_id AS entity_id, SUM(views) AS views
            FROM visitor_content_hourly
            WHERE
                (stat_date >= '" . e($since) . "')
                AND (item_type = '" . e($type) . "')
                AND (item_id > 0)
            GROUP BY item_id
        ) AS seo_traffic ON seo_traffic.entity_id = " . $id_expression;
}

// The impact figure, as select-list SQL to sit beside pg_seo_traffic_join().
//
// impact = (100 - score) x log10(1 + views)
//
// A list sorted by score alone is unusable on a real site: a page scoring 12
// that four people saw sits above a page scoring 38 that twelve thousand
// people saw, and the operator works down the list in the wrong order. The
// logarithm is what keeps traffic from swamping the score entirely - the
// difference between 100 and 1,000 views matters, the difference between
// 100,000 and 101,000 does not.
//
// NULL when the record has never been scored: an unknown score cannot be
// turned into a distance from perfect, and guessing would sort every new
// record either to the top or to the bottom for no reason.
function pg_seo_impact_select($score_column, $checked_column)
{
    if (!pg_seo_traffic_ready()) {
        return "NULL AS seo_impact, '0' AS seo_views";
    }

    // Two conditions, and both produce "we cannot say" rather than a number.
    //
    // seo_checked_at, not the score itself: a record that has never been
    // scored stores 0, which is indistinguishable from a genuinely terrible
    // one, and reading it as a distance from perfect would put every record
    // added this morning at the top of a list meant to say what to fix first.
    //
    // And a record nobody has visited. log10(1 + 0) is zero, so a page nobody
    // has seen and a page that is already perfect both come out at 0 - the
    // column would print the same thing for "no evidence" and for "nothing
    // left to gain", which are the two readings it exists to keep apart.
    return
        "IF((" . $checked_column . " > 0) AND (COALESCE(seo_traffic.views, 0) > 0),
            ROUND((100 - " . $score_column . ") * LOG10(1 + seo_traffic.views)),
            NULL) AS seo_impact,
        COALESCE(seo_traffic.views, 0) AS seo_views";
}

// The same figure as pg_seo_impact_select(), in PHP.
//
// A list screen that rescores stale rows after it has already read them holds
// a score the query never saw, and the impact beside it has to be recomputed
// or the row contradicts itself.
function pg_seo_impact_value($score, $views)
{
    // The same conditions that make the SQL form return NULL.
    if (($score === null) || ((int) $views < 1) || !pg_seo_traffic_ready()) {
        return null;
    }

    return (int) round((100 - (int) $score) * log10(1 + (int) $views));
}

// The impact cell for a list row: the figure, with the traffic it was weighed
// against underneath it.
function pg_seo_render_impact($row)
{
    if (!isset($row['seo_impact']) || ($row['seo_impact'] === null)) {
        return '<span class="text-body-secondary">&ndash;</span>';
    }

    $views = (int) ($row['seo_views'] ?? 0);

    return
        '<span class="fw-semibold">' . (int) $row['seo_impact'] . '</span>'
        . '<div class="small text-body-secondary">'
        . h(lang(array('string' => '{var:1} views', 'vars' => $views)))
        . '</div>';
}

// Mark records whose measured speed no longer matches the stored score.
//
// The other two groups are driven by editing: a record whose fields changed
// marks itself stale on the way out. Speed has no such moment - it changes
// because traffic arrived, or because the server got busier, with nobody
// touching the record. Without this pass a page that got slower would keep
// showing the score it had when it was last edited, until the periodic full
// refresh came round days later.
//
// Comparing before writing is the point. Marking every measured record stale
// nightly would rescore the whole site every night for a number that usually
// has not moved; marking only the ones that moved keeps the nightly run
// proportional to what actually changed.
//
// Returns the number of records queued.
function pg_seo_refresh_speed($type)
{
    if (!pg_seo_schema_ready() || !pg_seo_speed_schema_ready()) {
        return 0;
    }

    // The pass that reads what this queues clears a bounded number of rows per
    // run, so this has to be bounded too. Queue more than that and the stale
    // count never reaches zero - which does not just delay the work, it
    // switches off the periodic full refresh and the Recalculate button, both
    // of which only start when nothing is stale.
    //
    // Whatever is left over is picked up next run: a speed score that moved
    // today and is recorded tomorrow is not a problem, a queue that never
    // empties is.
    $limit = defined('SEO_SPEED_REFRESH_LIMIT') ? (int) SEO_SPEED_REFRESH_LIMIT : 300;

    if ($type == 'page') {
        $table = 'page';
        $id_column = 'page_id';
    } elseif ($type == 'product') {
        $table = 'products';
        $id_column = 'id';
    } else {
        $table = 'product_groups';
        $id_column = 'id';
    }

    $measured = pg_seo_speed_map($type);
    $queued = 0;

    // Records that currently carry a speed score are read too, not just the
    // measured ones: a record whose traffic dried up has to lose its score,
    // and it is absent from the map precisely because there is nothing left
    // to measure it with.
    $stored = array();

    foreach (db_items(
        "SELECT $id_column AS id, seo_speed_score
        FROM `$table`
        WHERE seo_speed_score IS NOT NULL") as $row
    ) {
        $stored[(int) $row['id']] = (int) $row['seo_speed_score'];
    }

    // Measurements outlive the record they were taken from: perf_stats keeps
    // its rows for the retention window, so a page deleted last week is still
    // in the map and would collect a nightly UPDATE that matches nothing.
    //
    // Only the measured ids are checked. A stored id came out of this table a
    // moment ago, so reading the whole table to confirm it exists would trade
    // a phantom UPDATE for the unbounded array pg_seo_recalculate() carries a
    // LIMIT specifically to avoid.
    $exists = array();

    foreach (array_keys($stored) as $stored_id) {
        $exists[$stored_id] = true;
    }

    $unchecked_ids = array_diff(array_keys($measured), array_keys($exists));

    if ($unchecked_ids) {
        foreach (db_items(
            "SELECT $id_column AS id
            FROM `$table`
            WHERE $id_column IN (" . implode(',', array_map('intval', $unchecked_ids)) . ")") as $row
        ) {
            $exists[(int) $row['id']] = true;
        }
    }

    $ids = array_unique(array_merge(array_keys($measured), array_keys($stored)));

    foreach ($ids as $id) {

        if (!isset($exists[$id])) {
            continue;
        }

        $speed = pg_seo_evaluate_speed(isset($measured[$id]) ? $measured[$id] : null);
        $was_scored = isset($stored[$id]);

        if ($speed['score'] === null) {

            // Nothing to say about this record any more. Clearing the column
            // is what takes the group back out of the denominator.
            if ($was_scored) {

                if ($queued >= $limit) {
                    break;
                }

                db(
                    "UPDATE `$table`
                    SET seo_speed_score = NULL, seo_analysis_current = 0
                    WHERE $id_column = '" . (int) $id . "'");
                $queued++;
            }

            continue;
        }

        if ($was_scored && ($stored[$id] === (int) $speed['score'])) {
            continue;
        }

        if ($queued >= $limit) {
            break;
        }

        db(
            "UPDATE `$table`
            SET seo_speed_score = '" . (int) $speed['score'] . "', seo_analysis_current = 0
            WHERE $id_column = '" . (int) $id . "'");

        $queued++;
    }

    return $queued;
}

// Recalculate scores and persist them.
//
// $ids null  -> process stale rows (seo_analysis_current = 0) in id order.
// $ids array -> process exactly those rows regardless of staleness.
// $time_budget (seconds, 0 = none) stops between rows once exceeded; the
// rows left over simply stay stale and are picked up by the next run or by
// the list screens, so the stale flag itself acts as the cursor and no
// bookkeeping column is needed.
//
// Returns array('items' => id => result, 'processed' => n, 'remaining' => n).
function pg_seo_recalculate($type, $ids = null, $time_budget = 0)
{
    // No writes before the upgrade added the columns they target.
    if (!pg_seo_schema_ready()) {
        return array('items' => array(), 'processed' => 0, 'remaining' => 0);
    }

    $started = microtime(true);
    $context = pg_seo_build_context($type, is_array($ids) ? $ids : null);

    // The stored structure score is read, never written, by this pass. The
    // column only joins the select when it exists, so the meta half keeps
    // working on an installation that has run 2026.4.11 but not 2026.4.12.
    $structure_ready = pg_seo_structure_schema_ready();
    $link_ready = pg_seo_link_schema_ready();
    $structure_column = ($structure_ready ? 'seo_struct_score,' : '') . ($link_ready ? ' seo_link_score,' : '');

    if ($type == 'page') {
        $table = 'page';
        $id_column = 'page_id';
        $select =
            "SELECT
                page.page_id,
                page.page_name,
                page.page_title,
                page.page_meta_description,
                page.page_search_keywords,
                page.page_search,
                page.page_type,
                page.sitemap,
                page.page_folder,
                " . ($structure_ready ? 'page.seo_struct_score,' : '') . ($link_ready ? ' page.seo_link_score,' : '') . "
                folder.folder_archived
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id";
    } elseif ($type == 'product') {
        $table = 'products';
        $id_column = 'id';
        $select =
            "SELECT
                id, name, title, meta_description, keywords, address_name,
                enabled, short_description, full_description, image_name,
                $structure_column
                brand, gtin, mpn
            FROM products";
    } else {
        $table = 'product_groups';
        $id_column = 'id';
        $select =
            "SELECT
                id, name, title, meta_description, keywords, address_name,
                $structure_column
                enabled, short_description, full_description, image_name
            FROM product_groups";
    }

    if (is_array($ids)) {
        $ids = array_filter(array_map('intval', $ids));

        if (!$ids) {
            return array('items' => array(), 'processed' => 0, 'remaining' => 0);
        }

        $where = " WHERE $table.$id_column IN (" . implode(',', $ids) . ")";
        $limit = '';
    } else {
        $where = " WHERE $table.seo_analysis_current = 0";

        // The stale set has to be bounded in the query, not only by the
        // clock. The select carries longtext columns (full_description), so
        // without a LIMIT the whole backlog is materialised into a PHP array
        // before the budget is consulted even once - and the run that most
        // needs the budget, the first one after a site-wide invalidation, is
        // exactly the one that would exhaust memory and write nothing.
        $limit = ' LIMIT 500';
    }

    $rows = db_items($select . $where . " ORDER BY $table.$id_column ASC" . $limit);

    $items = array();
    $processed = 0;

    foreach ($rows as $row) {

        // Checked after the first row, never before it: the context build
        // above can outlast a small budget on a large site, and breaking on
        // iteration zero would burn the whole cost every run and never
        // advance.
        if ($processed && $time_budget && ((microtime(true) - $started) > $time_budget)) {
            break;
        }

        if ($type == 'page') {
            // Folder access control resolves through the parent chain, so the
            // joined row alone cannot answer it; the resolver memoizes per
            // request, which keeps this loop at one folder walk per folder.
            $row['folder_public'] = (get_access_control_type($row['page_folder']) == 'public');
        }

        $entity = pg_seo_normalize_entity($type, $row);
        $result = pg_seo_evaluate_meta($entity, $context);

        // The structure half is computed by a separate, much more expensive
        // pass. This one reads its stored result rather than recomputing it,
        // so that saving a title does not force the page to be rendered
        // again - and so that neither pass can wipe the other's work.
        $structure_score = null;
        $link_score = null;
        $sql_structure = '';

        if ($structure_ready) {
            $structure_score = isset($row['seo_struct_score']) && ($row['seo_struct_score'] !== null)
                ? (int) $row['seo_struct_score']
                : null;

            $link_score = isset($row['seo_link_score']) && ($row['seo_link_score'] !== null)
                ? (int) $row['seo_link_score']
                : null;

            $result['analysis']['groups']['structure'] = $structure_score;
            $result['analysis']['groups']['links'] = $link_score;
            $sql_structure = "seo_meta_score = '" . (int) $result['score'] . "',";
        }

        // The speed half. Unlike structure it is not read back from a stored
        // column: it is derived from measurements that keep arriving, so the
        // freshest answer is the one computed here. The column it writes
        // exists so the nightly pass can tell whether the answer changed
        // without scoring the record again.
        $speed = pg_seo_evaluate_speed(
            isset($context['speed'][$entity['id']]) ? $context['speed'][$entity['id']] : null);

        $speed_score = $speed['score'];
        $sql_speed = '';

        if (pg_seo_speed_schema_ready()) {
            $sql_speed =
                "seo_speed_score = " . (($speed_score === null) ? 'NULL' : "'" . (int) $speed_score . "'") . ",";
        }

        if ($speed_score !== null) {
            $result['analysis']['groups']['speed'] = $speed_score;
            $result['analysis']['checks'] = array_merge($result['analysis']['checks'], $speed['checks']);
            $result['flags'] |= $speed['flags'];
        }

        if ($structure_ready) {
            $result['analysis']['weights'] = pg_seo_group_weights($structure_score, $link_score, $speed_score);
        }

        // Structure bits ride along in the same column. They were derived
        // from seo_issue once for the whole run, so this costs a lookup.
        if (isset($context['structure_flags'][$entity['id']])) {
            $result['flags'] |= (int) $context['structure_flags'][$entity['id']];
        }

        // From here on 'score' is the composed number the screens display.
        // The meta half it was built from stays readable in
        // analysis.groups.meta, which evaluate_meta already filled in.
        $composed = pg_seo_compose($result['score'], $structure_score, $link_score, $speed_score);
        $result['analysis']['score'] = $composed;
        $result['score'] = $composed;

        db(
            "UPDATE $table
            SET
                seo_score = '" . (int) $composed . "',
                $sql_structure
                $sql_speed
                seo_flags = '" . (int) $result['flags'] . "',
                seo_analysis = '" . e(encode_json($result['analysis'])) . "',
                seo_analysis_current = '1',
                seo_checked_at = UNIX_TIMESTAMP()
            WHERE $id_column = '" . (int) $entity['id'] . "'");

        $items[$entity['id']] = $result;
        $processed++;
    }

    // Only the backlog-draining callers report progress, and only they pay
    // for the count. A caller that named its records already knows how many
    // there were.
    $remaining = is_array($ids)
        ? 0
        : (int) db_value("SELECT COUNT(*) FROM $table WHERE seo_analysis_current = 0");

    return array('items' => $items, 'processed' => $processed, 'remaining' => $remaining);
}

// Whether a row has a score worth showing.
//
// Deliberately NOT seo_analysis_current: that flag means "the stored score
// may be out of date", which is a different statement from "there is no
// score". A nightly refresh marks every record stale for a few minutes, and
// keying the display off it made the whole site read as uncalculated until
// the pass caught up - hiding scores that were perfectly good. seo_checked_at
// stays at zero only until a record has been scored once, which is the
// question the display is actually asking.
//
// Rows selected before that column existed fall back to the flag.
function pg_seo_row_scored($row)
{
    if (isset($row['seo_checked_at'])) {
        return ((int) $row['seo_checked_at']) > 0;
    }

    return ((string) ($row['seo_analysis_current'] ?? '')) === '1';
}

// Color band for a score, shared by every screen that draws one so the bands
// cannot drift apart. Bootstrap has no orange contextual class, hence the
// style fallback for the third band.
function pg_seo_score_color($score)
{
    if ($score >= 80) {
        return array('class' => 'success', 'style' => '');
    }

    if ($score >= 55) {
        return array('class' => 'warning', 'style' => '');
    }

    if ($score >= 30) {
        return array('class' => '', 'style' => 'var(--bs-orange)');
    }

    return array('class' => 'danger', 'style' => '');
}

// Thin progress bar with the score at its side, drawn under the record name
// in list screens. Expects seo_score, seo_flags, seo_analysis_current keys.
function pg_seo_render_bar($row)
{
    if (!pg_seo_row_scored($row)) {
        return
            '<div class="d-flex align-items-center gap-2 mt-1" style="max-width:170px" title="' . h(lang('SEO score has not been calculated yet.')) . '">
                <div class="progress flex-grow-1" style="height:3px"><div class="progress-bar bg-secondary opacity-25" style="width:100%"></div></div>
                <small class="text-secondary fw-semibold">&mdash;</small>
            </div>';
    }

    $score = (int) $row['seo_score'];
    $color = pg_seo_score_color($score);
    $bar_style = 'width:' . $score . '%';
    $text_style = '';

    if ($color['style'] !== '') {
        $bar_style .= ';background-color:' . $color['style'];
        $text_style = ' style="color:' . $color['style'] . '"';
    }

    $labels = pg_seo_flag_labels((int) ($row['seo_flags'] ?? 0), 3);
    $title = lang('SEO Score') . ': ' . $score . '/100';

    if ($labels) {
        $title .= ' — ' . implode(' · ', $labels);
    }

    $bar_class = ($color['class'] !== '') ? ' bg-' . $color['class'] : '';
    $text_class = ($color['class'] !== '') ? ' text-' . $color['class'] : '';

    return
        '<div class="d-flex align-items-center gap-2 mt-1" style="max-width:170px" title="' . h($title) . '">
            <div class="progress flex-grow-1" style="height:3px"><div class="progress-bar' . $bar_class . '" style="' . h($bar_style) . '"></div></div>
            <small class="fw-semibold' . $text_class . '"' . $text_style . '>' . $score . '</small>
        </div>';
}

// Human line for one stored check row, with its detail values filled in.
function pg_seo_check_label($check)
{
    $d = isset($check['d']) ? $check['d'] : array();

    // Several labels have to state the failing case rather than the passing
    // one. The icon carries pass or fail, but a line reading "Title is
    // filled in" next to a zero score reads as a contradiction, and the
    // operator is looking at this list precisely to find what is missing.
    switch ($check['c']) {
        case 'title_present':
            return ($check['s'] == 'ok') ? lang('Title is filled in') : lang('Title is empty');

        case 'title_length':
            return lang(array('string' => 'Title length: {var:1} character{suffix:1} (50-60 is ideal)', 'vars' => (int) ($d['len'] ?? 0), 'suffix' => ((($d['len'] ?? 0) == 1) ? '' : 's')));

        case 'title_unique':
            if ($check['s'] == 'ok') {
                return lang('Title is unique');
            }

            return lang(array('string' => 'Title is used by {var:1} records', 'vars' => (int) ($d['copies'] ?? 2)));

        case 'title_distinct':
            return ($check['s'] == 'ok') ? lang('Title is not a filler value') : lang('Title only repeats the name or the site title');

        case 'desc_present':
            return ($check['s'] == 'ok') ? lang('Description is filled in') : lang('Description is empty');

        case 'desc_length':
            return lang(array('string' => 'Description length: {var:1} character{suffix:1} (150-160 is ideal)', 'vars' => (int) ($d['len'] ?? 0), 'suffix' => ((($d['len'] ?? 0) == 1) ? '' : 's')));

        case 'desc_unique':
            if ($check['s'] == 'ok') {
                return lang('Description is unique');
            }

            return lang(array('string' => 'Description is used by {var:1} records', 'vars' => (int) ($d['copies'] ?? 2)));

        case 'keywords':
            // An empty list is the one worth explaining: the field is easy to
            // miss and it is the only thing that puts the record in the tag
            // cloud and promotes it in the site search.
            if (((int) ($d['terms'] ?? 0)) == 0) {
                return lang('No promoted keywords: add 3-8 to surface this record in site search and in the tag cloud');
            }

            if ($check['s'] == 'ok') {
                return lang(array('string' => 'Promoted keywords: {var:1} terms', 'vars' => (int) $d['terms']));
            }

            return lang(array('string' => 'Promoted keywords: {var:1} term{suffix:1} (3-8 is ideal)', 'vars' => (int) $d['terms'], 'suffix' => ((((int) $d['terms']) == 1) ? '' : 's')));

        case 'speed_response':
            return lang(array(
                'string' => 'Average server response: {var:1} ms over {var:2} requests (under 800 ms is the target)',
                'vars' => array((int) ($d['ms'] ?? 0), (int) ($d['hits'] ?? 0))));

        case 'speed_slow_ratio':
            if ($check['s'] == 'ok') {
                return lang(array(
                    'string' => 'Slow requests: {var:1}% of {var:2}',
                    'vars' => array($d['percent'] ?? 0, (int) ($d['hits'] ?? 0))));
            }

            return lang(array(
                'string' => '{var:1} of {var:2} requests took longer than {var:3} ms ({var:4}%)',
                'vars' => array(
                    (int) ($d['slow'] ?? 0),
                    (int) ($d['hits'] ?? 0),
                    (int) ($d['threshold'] ?? 1000),
                    $d['percent'] ?? 0)));

        case 'speed_memory':
            return lang(array(
                'string' => 'Average memory per request: {var:1} MB',
                'vars' => round(((int) ($d['kb'] ?? 0)) / 1024, 1)));

        case 'slug':
            if ($check['s'] == 'ok') {
                return lang('Name is URL-friendly');
            }

            $problems = isset($d['problems']) ? (array) $d['problems'] : array();

            // Non-ASCII first: on IIS that is not a style preference, it is a
            // page that cannot be reached by its own address.
            if (in_array('non_ascii', $problems)) {
                return lang('Name contains non-ASCII characters and may not be reachable');
            }

            if (in_array('empty', $problems)) {
                return lang('No catalog name set, one is generated from the item name');
            }

            return lang('Name contains spaces, underscores or is very long');

        case 'indexable':
            if ($check['s'] == 'ok') {
                return lang('In the site map and publicly accessible');
            }

            if (($d['reason'] ?? '') == 'archived') {
                return lang('Marked for site map but the folder is archived');
            }

            return lang('Marked for site map but the folder is not public');

        case 'short_description':
            return ($check['s'] == 'ok') ? lang('Short description is filled in') : lang('Short description is empty');

        case 'description_volume':
            return lang(array('string' => 'Full description: {var:1} word{suffix:1} (150+ is ideal)', 'vars' => (int) ($d['words'] ?? 0), 'suffix' => ((($d['words'] ?? 0) == 1) ? '' : 's')));

        case 'image':
            return ($check['s'] == 'ok') ? lang('Product image is set') : lang('No product image');

        case 'structured_fields':
            return lang(array('string' => 'Brand / GTIN / MPN: {var:1} of 3 filled in', 'vars' => (int) ($d['filled'] ?? 0)));

        case 'source_products':
            if (($d['reason'] ?? '') == 'no_products') {
                return lang('The linked product group has no enabled products');
            }

            return lang(array(
                'string' => '{var:1} of {var:2} linked products have a title and a description ({var:3} are missing one or both)',
                'vars' => array((int) ($d['complete'] ?? 0), (int) ($d['products'] ?? 0), (int) ($d['thin'] ?? 0))));

        case 'source_unresolved':
            return lang('This page inherits its title and description, but no source is linked to it');

        case 'source_form_fields':
            if ($check['s'] == 'ok') {
                return lang('Form fields provide the title and description');
            }

            if ($check['s'] == 'warn') {
                return lang('A form field provides the title, but none provides the description');
            }

            return lang('No form field is marked as the title or description source');
    }

    return $check['c'];
}

// Human label for one stored structure finding. The occurrence count is
// folded into the sentence where it changes what the sentence means -
// "3 images have no alt text" is a different statement from "an image has
// no alt text" - and left out where the finding is a single fact about the
// document, like a missing title tag.
function pg_seo_issue_label($code, $occurrences = 1, $detail = '')
{
    $count = max(1, (int) $occurrences);

    switch ($code) {
        case 'title_tag_missing':
            return lang('The page has no <title> tag at all');
        case 'title_tag_multiple':
            return lang('The page has more than one <title> tag');
        case 'meta_description_tag_missing':
            return lang('The page emits no meta description tag');
        case 'canonical_missing':
            return lang('No canonical link');
        case 'canonical_multiple':
            return lang('More than one canonical link');
        case 'noindex_but_in_sitemap':
            return lang('Marked noindex although it is in the site map');
        case 'viewport_missing':
            return lang('No viewport tag (mobile rendering)');
        case 'og_incomplete':
            return lang('Social sharing tags are incomplete');

        case 'h1_missing':
            return lang('No H1 heading');
        case 'h1_multiple':
            return lang(array('string' => '{var:1} extra H1 heading{suffix:1}', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'h1_empty':
            return lang('The H1 heading has no text');
        case 'h1_equals_title':
            return lang('The H1 heading repeats the browser title');
        case 'h1_too_long':
            return lang('The H1 heading is very long');
        case 'heading_starts_at_h2':
            return lang('Headings start below H1');
        case 'heading_level_skip':
            return lang(array('string' => 'Heading levels skipped {var:1} time{suffix:1}', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'heading_empty':
            return lang(array('string' => '{var:1} empty heading tag{suffix:1}', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'heading_is_paragraph':
            return lang('A heading tag holds a paragraph of text');
        case 'heading_duplicate_text':
            return lang('The same heading text is used more than once');

        case 'p_contains_block':
            return lang(array('string' => '{var:1} paragraph{suffix:1} contain a block element (invalid nesting)', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'p_empty':
            return lang(array('string' => '{var:1} empty paragraph{suffix:1}', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'br_as_paragraph':
            return lang('Line breaks used instead of paragraphs');
        case 'presentational_tag':
            return lang('Obsolete presentational tags are used');
        case 'bold_italic_tag':
            return lang('<b> or <i> used instead of <strong> or <em>');
        case 'inline_style_heavy':
            return lang('Heavy use of inline style attributes');

        case 'list_invalid_child':
            return lang('A list contains something other than list items');
        case 'table_no_header':
            return lang('A data table has no header row or caption');

        case 'img_no_alt':
            return lang(array('string' => '{var:1} image{suffix:1} without alt text', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'img_alt_is_filename':
            return lang(array('string' => '{var:1} image{suffix:1} use the file name as alt text', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'img_no_dimensions':
            return lang(array('string' => '{var:1} image{suffix:1} without width and height', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'img_no_lazy':
            return lang(array('string' => '{var:1} image{suffix:1} not lazily loaded', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));

        case 'link_empty':
            return lang(array('string' => '{var:1} link{suffix:1} with no text', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'link_empty_href':
            return lang(array('string' => '{var:1} link{suffix:1} with an empty address', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'link_generic_anchor':
            return lang(array('string' => '{var:1} link{suffix:1} with generic wording', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'link_blank_no_noopener':
            return lang(array('string' => '{var:1} new-window link{suffix:1} without rel="noopener"', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));

        case 'html_lang_missing':
            return lang('The page declares no language');
        case 'main_missing':
            return lang('No <main> region');
        case 'main_multiple':
            return lang('More than one <main> region');
        case 'landmark_missing':
            return lang('No navigation, header or footer regions');
        case 'input_no_label':
            return lang(array('string' => '{var:1} form field{suffix:1} without a label', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'iframe_no_title':
            return lang('An embedded frame has no title');
        case 'button_empty':
            return lang('A button has no readable text');

        case 'thin_content':
            return lang(array('string' => 'Thin content: {var:1} words (300+ recommended)', 'vars' => (int) $detail));
        case 'low_text_ratio':
            return lang('Very little text compared to markup');

        case 'jsonld_invalid':
            return lang('Structured data is not valid JSON');
        case 'jsonld_missing_type':
            return lang('Structured data has no @type');
        case 'jsonld_missing':
            return lang('No structured data on a catalog page');
        case 'product_schema_incomplete':
            return lang('Product structured data has no price or availability');

        case 'h1_only_in_template':
            return lang('The only H1 comes from the page style, not the content');

        case 'link_broken_internal':
            return lang(array('string' => '{var:1} internal link{suffix:1} point nowhere', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'link_to_private':
            return lang(array('string' => '{var:1} link{suffix:1} to a page visitors cannot reach', 'vars' => $count, 'suffix' => (($count == 1) ? '' : 's')));
        case 'orphan_page':
            return lang('In the site map but nothing links to it');
        case 'depth_too_deep':
            return lang(array('string' => '{var:1} clicks from the home page', 'vars' => (int) $detail));
        case 'anchor_inconsistent':
            return lang('The same destination is linked with very different wording');
    }

    return $code;
}

// Stored structure findings for one record, worst first.
function pg_seo_load_issues($type, $id)
{
    if (!pg_seo_structure_schema_ready()) {
        return array();
    }

    return db_items(
        "SELECT code, severity, occurrences, source, detail
        FROM seo_issue
        WHERE (entity_type = '" . e($type) . "') AND (entity_id = '" . (int) $id . "')
        ORDER BY FIELD(severity, 'error', 'warning', 'notice'), occurrences DESC");
}

// Detail panel for the edit screens and the list offcanvas: a score bar, the
// two group scores, the meta checks and the structure findings.
//
// Renders from the persisted analysis, so it shows the record as last saved.
// The live character counters above the fields keep showing the unsaved
// state, and the difference between the two is itself the signal that there
// are unsaved changes.
function pg_seo_render_checklist($row, $type = '', $id = 0)
{
    if (!pg_seo_row_scored($row)) {
        return
            '<div class="form-text mb-0">
                <span class="bi bi-hourglass-split me-1"></span>' . lang('The SEO score will be calculated after the next save or list visit.') . '
            </div>';
    }

    $analysis = json_decode((string) ($row['seo_analysis'] ?? ''), true);

    if (!is_array($analysis) || empty($analysis['checks'])) {
        return '';
    }

    $score = (int) $row['seo_score'];
    $color = pg_seo_score_color($score);
    $bar_class = ($color['class'] !== '') ? ' bg-' . $color['class'] : '';
    $bar_style = 'width:' . $score . '%' . (($color['style'] !== '') ? ';background-color:' . $color['style'] : '');
    $text_class = ($color['class'] !== '') ? ' text-' . $color['class'] : '';
    $text_style = ($color['style'] !== '') ? ' style="color:' . $color['style'] . '"' : '';

    // Speed rows are kept apart from the rest. They describe how the page is
    // delivered rather than what is written on it, and they are measured from
    // real traffic rather than read off the record, so mixing them into the
    // same list would invite an operator to fix them by editing a field.
    $output_rows = '';
    $output_speed_rows = '';

    foreach ($analysis['checks'] as $check) {
        if ($check['s'] == 'ok') {
            $icon = '<span class="bi bi-check-circle-fill text-success me-2"></span>';
        } elseif ($check['s'] == 'warn') {
            $icon = '<span class="bi bi-exclamation-triangle-fill text-warning me-2"></span>';
        } else {
            $icon = '<span class="bi bi-x-circle-fill text-danger me-2"></span>';
        }

        $output_row =
            '<div class="d-flex align-items-center py-1 border-bottom border-light">
                ' . $icon . '
                <span class="flex-grow-1 small">' . h(pg_seo_check_label($check)) . '</span>
                <span class="small text-body-secondary ms-2">' . (int) $check['e'] . '/' . (int) $check['w'] . '</span>
            </div>';

        if (strpos((string) $check['c'], 'speed_') === 0) {
            $output_speed_rows .= $output_row;
        } else {
            $output_rows .= $output_row;
        }
    }

    // Group breakdown. Only drawn once a second group exists for this record:
    // before that the composed score is the meta score, and a bar reading the
    // same number twice explains nothing.
    $structure_score = isset($analysis['groups']['structure']) ? $analysis['groups']['structure'] : null;
    $link_score = isset($analysis['groups']['links']) ? $analysis['groups']['links'] : null;
    $speed_score = isset($analysis['groups']['speed']) ? $analysis['groups']['speed'] : null;
    $meta_score = isset($analysis['groups']['meta']) ? (int) $analysis['groups']['meta'] : $score;
    $output_groups = '';

    if (($structure_score !== null) || ($speed_score !== null)) {
        $weights = pg_seo_group_weights($structure_score, $link_score, $speed_score);

        $output_groups = pg_seo_render_group_row(lang('Content & Meta'), (int) $meta_score, $weights['meta']);

        if ($structure_score !== null) {
            $output_groups .= pg_seo_render_group_row(lang('HTML Structure'), (int) $structure_score, $weights['structure']);
        }

        if ($link_score !== null) {
            $output_groups .= pg_seo_render_group_row(lang('Internal Links'), (int) $link_score, $weights['links']);
        }

        if ($speed_score !== null) {
            $output_groups .= pg_seo_render_group_row(lang('Page Speed'), (int) $speed_score, $weights['speed']);
        }
    }

    if ($output_speed_rows !== '') {
        $output_speed_rows =
            '<div class="fw-semibold small text-uppercase text-body-secondary mt-3 mb-1">' . lang('Page Speed') . '</div>'
            . $output_speed_rows;
    }

    // Structure findings, read from their own table because there can be any
    // number of them per record.
    $output_issues = '';

    if ($type && $id) {
        $issues = pg_seo_load_issues($type, $id);

        if ($issues) {
            $output_issues = '<div class="fw-semibold small text-uppercase text-body-secondary mt-3 mb-1">' . lang('HTML Structure') . '</div>';

            foreach ($issues as $issue) {
                if ($issue['severity'] == 'error') {
                    $icon = '<span class="bi bi-x-circle-fill text-danger me-2"></span>';
                } elseif ($issue['severity'] == 'warning') {
                    $icon = '<span class="bi bi-exclamation-triangle-fill text-warning me-2"></span>';
                } else {
                    $icon = '<span class="bi bi-dash-circle text-body-secondary me-2"></span>';
                }

                $source_label = function_exists('pg_seo_source_label') ? pg_seo_source_label($issue['source']) : '';
                $output_source = ($source_label !== '')
                    ? '<span class="badge text-bg-light fw-normal ms-2">' . h($source_label) . '</span>'
                    : '';

                $output_issues .=
                    '<div class="d-flex align-items-center py-1 border-bottom border-light">
                        ' . $icon . '
                        <span class="flex-grow-1 small"' . (($issue['detail'] !== '') ? ' title="' . h($issue['detail']) . '"' : '') . '>'
                            . h(pg_seo_issue_label($issue['code'], $issue['occurrences'], $issue['detail'])) . '</span>
                        ' . $output_source . '
                    </div>';
            }
        }
    }

    return
        '<div class="mt-2">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="fw-bold' . $text_class . '"' . $text_style . '>' . $score . '/100</span>
                <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar' . $bar_class . '" style="' . h($bar_style) . '"></div></div>
            </div>
            ' . $output_groups . '
            <div class="form-text mt-0 mb-2">' . lang('The score below reflects the last saved values.') . '</div>
            <div class="fw-semibold small text-uppercase text-body-secondary mb-1">' . lang('Content & Meta') . '</div>
            ' . $output_rows . '
            ' . $output_issues . '
            ' . $output_speed_rows . '
        </div>';
}

// One group line in the detail panel: name, thin bar, score and the weight
// it carries in the composed total.
function pg_seo_render_group_row($label, $score, $weight)
{
    $color = pg_seo_score_color($score);
    $bar_class = ($color['class'] !== '') ? ' bg-' . $color['class'] : '';
    $bar_style = 'width:' . (int) $score . '%' . (($color['style'] !== '') ? ';background-color:' . $color['style'] : '');

    return
        '<div class="d-flex align-items-center gap-2 mb-1">
            <span class="small text-body-secondary" style="min-width:110px">' . h($label) . '</span>
            <div class="progress flex-grow-1" style="height:4px"><div class="progress-bar' . $bar_class . '" style="' . h($bar_style) . '"></div></div>
            <span class="small fw-semibold" style="min-width:28px;text-align:right">' . (int) $score . '</span>
            <span class="small text-body-secondary">%' . (int) $weight . '</span>
        </div>';
}

// Offcanvas shell and the delegated click handler for the SEO detail panel
// on list screens. Any element with class pg-seo-open and a data-seo-url
// attribute opens the panel; the URL returns the fragment rendered by
// get_seo_analysis.php.
function pg_seo_render_detail_offcanvas()
{
    return
    '<div class="offcanvas offcanvas-end" tabindex="-1" id="pg_seo_offcanvas" aria-labelledby="pg_seo_offcanvas_title" style="width:420px;max-width:100%">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="pg_seo_offcanvas_title"><span class="bi bi-graph-up-arrow me-2"></span>' . lang('SEO Detail') . '</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . h(lang('Close')) . '"></button>
        </div>
        <div class="offcanvas-body" id="pg_seo_offcanvas_body"></div>
    </div>
    <script>
    document.addEventListener("click", function (event) {
        var trigger = event.target.closest(".pg-seo-open");

        if (!trigger) {
            return;
        }

        event.preventDefault();

        var offcanvas_element = document.getElementById("pg_seo_offcanvas");
        var body = document.getElementById("pg_seo_offcanvas_body");

        body.innerHTML = "<div class=\"text-center py-4\"><div class=\"spinner-border text-primary\" role=\"status\"></div></div>";
        bootstrap.Offcanvas.getOrCreateInstance(offcanvas_element).show();

        fetch(trigger.getAttribute("data-seo-url"), {credentials: "same-origin"})
            .then(function (response) { return response.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () { body.innerHTML = "<div class=\"text-danger\">" + ' . json_encode(lang('An error occurred')) . ' + "</div>"; });
    });
    </script>';
}

// Compact badge for the SEO column of list screens: the score in the band
// color, with the worst findings as the tooltip.
function pg_seo_render_badge($row)
{
    if (!pg_seo_row_scored($row)) {
        return '<span class="badge text-bg-secondary opacity-50" title="' . h(lang('SEO score has not been calculated yet.')) . '">&mdash;</span>';
    }

    $score = (int) $row['seo_score'];
    $color = pg_seo_score_color($score);
    $labels = pg_seo_flag_labels((int) ($row['seo_flags'] ?? 0), 3);
    $title = $labels ? implode(' · ', $labels) : lang('No SEO problems were found.');

    if ($color['class'] !== '') {
        return '<span class="badge text-bg-' . $color['class'] . '" title="' . h($title) . '">' . $score . '</span>';
    }

    return '<span class="badge" style="background-color:' . $color['style'] . '" title="' . h($title) . '">' . $score . '</span>';
}
