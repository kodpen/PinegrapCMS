/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Publish check's findings: what the designer's existing checks say
 * about a page, read into one shape the Publish sheet lists in five groups
 * (Links, Accessibility, Search engines, Weight, Content). Pure: no DOM, no
 * request; the checks run where they always ran and hand their answers in.
 *
 * The sources read():
 *   audit     validateTree()'s issues: { rule, label, node, nodeLabel, seo }
 *   seo       designer/seo_check's answer: checks (the meta checks), findings,
 *             a11y (the rendered draft's rows, null without one), concepts
 *             (the audit rule registry, to keep one concept once)
 *   links     PgProtoHotspots.canvasMarks() of the page:
 *             { nodeId, sharedId, source, index, mark, label, href, why }
 *   unreached whether no link of the design leads to the page
 *   leftovers values a place keeps for a component field closed since:
 *             { node, shared, field, label }
 * A source left out (undefined or null) was not asked for; `false`, or an
 * SEO answer whose status is not success, is a check that failed to run: it
 * says so in its group and blocks nothing.
 *
 * A finding:
 *   { id, source, code, group, level: 'block'|'look'|'failed', text, node,
 *     shared, where, count, detail, seo, concept }
 * `id` is stable for the same finding on the same element (source, code,
 * node, the nth link of a text), so a choice made about it can be kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
var PgPublishFindings = (function () {
    'use strict';

    var GROUPS = ['links', 'a11y', 'seo', 'weight', 'content'];

    // What blocks a page, by source and code: a link to a page that is gone,
    // a form sent nowhere, a page without a title (its title field, its
    // <title> tag, or the accessibility side of the same), a value left
    // behind by a component field closed since. Short and fixed.
    var BLOCKING = [
        'links:link_broken', 'audit:form_no_action', 'seo:title_present', 'seo:title_tag_missing',
        'a11y:a11y_page_title', 'component:field_leftover'
    ];

    // The group of an audit rule that is not about accessibility.
    var AUDIT_GROUP = {
        link_blank_noopener: 'links',
        form_no_action: 'links',
        h1_missing: 'seo',
        h1_multiple: 'seo',
        heading_starts_at_h2: 'seo',
        img_alt_filename: 'seo',
        presentational_tag: 'seo',
        cf_field_unnamed: 'content',
        mail_block: 'content'
    };

    // The English sentences of the codes no check words itself; the sheet
    // hands its translated texts (`texts`, code => sentence) in their place.
    var TEXT = {
        link_broken: 'The link leads to a page that is gone',
        link_empty: 'The link has no target',
        page_unreached: 'No link of the design leads to this page',
        field_leftover: 'A value is kept for a component field that is closed',
        audit_failed: 'The page check did not run',
        link_failed: 'The links could not be checked',
        seo_failed: 'The search engine check did not run',
        a11y_failed: 'The accessibility check did not run',
        component_failed: 'The component fields could not be checked'
    };

    function str(v) {
        return (v === null || v === undefined) ? '' : String(v);
    }

    function text(code, texts, fallback) {
        if (texts && typeof texts[code] === 'string' && texts[code] !== '') {
            return texts[code];
        }

        return str(fallback) !== '' ? str(fallback) : (TEXT[code] || code);
    }

    function blocks(source, code) {
        return BLOCKING.indexOf(source + ':' + code) !== -1;
    }

    // One finding; `f` names what differs from the defaults.
    function make(source, code, group, f) {
        var node = str(f.node);
        var index = parseInt(f.index, 10) || 0;

        return {
            id: source + ':' + code + ':' + node + ':' + index,
            source: source,
            code: code,
            group: group,
            level: f.level || (blocks(source, code) ? 'block' : 'look'),
            text: str(f.text),
            node: node,
            shared: parseInt(f.shared, 10) || 0,
            where: str(f.where),
            count: Math.max(1, parseInt(f.count, 10) || 1),
            detail: str(f.detail),
            seo: str(f.seo),
            concept: str(f.concept)
        };
    }

    function list(v) {
        return Array.isArray(v) ? v : [];
    }

    function isObject(v) {
        return !!v && typeof v === 'object' && !Array.isArray(v);
    }

    function failed(source, code, group, detail, texts) {
        return make(source, code, group, { level: 'failed', text: text(code, texts), detail: detail });
    }

    // validateTree()'s issues.
    function fromAudit(issues) {
        return list(issues).filter(isObject).filter(function (i) { return str(i.rule) !== ''; }).map(function (i) {
            var rule = str(i.rule);

            return make('audit', rule, AUDIT_GROUP[rule] || 'a11y', {
                text: i.label,
                node: (i.node && i.node._id !== undefined) ? i.node._id : '',
                where: i.nodeLabel,
                seo: i.seo
            });
        });
    }

    // The SEO check's answer: a meta check that failed, and every finding.
    // A warned meta check (few keywords, a long title) is the score's
    // business, not something to look at before publishing.
    function fromSeo(answer) {
        if (!isObject(answer)) {
            return [];
        }

        var out = list(answer.checks).filter(function (c) { return isObject(c) && c.status === 'fail'; }).map(function (c) {
            return make('seo', str(c.code), 'seo', { text: c.label });
        });

        list(answer.findings).filter(isObject).forEach(function (f) {
            out.push(make('seo', str(f.code), 'seo', { text: f.label, count: f.occurrences, detail: f.detail }));
        });

        return out;
    }

    // The rendered draft's accessibility rows, each on its first node.
    function fromA11y(rows) {
        return list(rows).filter(isObject).map(function (r) {
            return make('a11y', str(r.code), 'a11y', {
                text: r.label,
                node: list(r.nodes).length ? r.nodes[0] : '',
                count: r.occurrences,
                detail: r.detail,
                concept: r.concept
            });
        });
    }

    // The canvas link marks of the page and whether a link leads to it.
    function fromLinks(marks, opts, texts) {
        var out = list(marks).filter(isObject).map(function (m) {
            var code = (m.mark === 'broken') ? 'link_broken' : 'link_empty';

            return make('links', code, 'links', {
                text: text(code, texts),
                node: m.nodeId,
                shared: m.sharedId,
                index: m.index,
                where: m.label,
                detail: m.href
            });
        });

        if (opts && opts.unreached) {
            out.push(make('links', 'page_unreached', 'links', { text: text('page_unreached', texts) }));
        }

        return out;
    }

    // Values a place keeps for a component field closed since.
    function fromLeftovers(rows, texts) {
        return list(rows).filter(isObject).map(function (r) {
            return make('component', 'field_leftover', 'content', {
                text: text('field_leftover', texts),
                node: r.node,
                shared: r.shared,
                where: r.label,
                detail: r.field
            });
        });
    }

    // concept => editor rules, from the registry rows; code => concept.
    function conceptMaps(concepts) {
        var editor = {};
        var byCode = {};

        list(concepts).filter(isObject).forEach(function (c) {
            var name = str(c.concept);

            if (name === '') {
                return;
            }

            editor[name] = list(c.editor).map(str);

            if (str(c.seo) !== '') {
                byCode['seo:' + c.seo] = name;
            }

            if (str(c.a11y) !== '') {
                byCode['a11y:' + c.a11y] = name;
            }
        });

        return { editor: editor, byCode: byCode };
    }

    // The same concept found by more than one check is kept once: the
    // canvas audit names the element, then the draft's accessibility row,
    // then the SEO finding. Kept, it blocks when any of its copies does.
    function merge(found, concepts) {
        var maps = conceptMaps(concepts);
        var rules = {};
        var seoByAudit = {};
        var kept = {};
        var out = [];

        found.forEach(function (f) {
            if (f.source === 'audit') {
                rules[f.code] = true;

                if (f.seo !== '') {
                    seoByAudit[f.seo] = true;
                }
            }
        });

        found.forEach(function (f) {
            if (f.source !== 'seo' && f.source !== 'a11y') {
                out.push(f);
                return;
            }

            var concept = f.concept || maps.byCode[f.source + ':' + f.code] || '';

            if (f.source === 'seo' && seoByAudit[f.code]) {
                return;
            }

            if (concept !== '' && (maps.editor[concept] || []).some(function (r) { return rules[r]; })) {
                return;
            }

            if (concept === '') {
                out.push(f);
                return;
            }

            if (kept[concept]) {
                if (f.level === 'block') {
                    kept[concept].level = 'block';
                }

                return;
            }

            f.concept = concept;
            kept[concept] = f;
            out.push(f);
        });

        return out;
    }

    // Blocking first, then the groups' order, each group in the order found.
    function sort(found) {
        return found.map(function (f, i) { return { f: f, i: i }; }).sort(function (a, b) {
            var ab = (a.f.level === 'block') ? 0 : 1;
            var bb = (b.f.level === 'block') ? 0 : 1;

            if (ab !== bb) {
                return ab - bb;
            }

            var ag = GROUPS.indexOf(a.f.group);
            var bg = GROUPS.indexOf(b.f.group);

            return (ag !== bg) ? ag - bg : a.i - b.i;
        }).map(function (x) { return x.f; });
    }

    // Every source's findings for one page; see the file's head.
    function read(sources, texts) {
        var s = isObject(sources) ? sources : {};
        var found = [];
        var seo = s.seo;

        // The audit runs first and wins a merged concept, then the draft's
        // accessibility rows, then the SEO findings.
        if (s.audit === false) {
            found.push(failed('audit', 'audit_failed', 'a11y', '', texts));
        } else {
            found = found.concat(fromAudit(s.audit));
        }

        if (seo === false || (isObject(seo) && seo.status !== 'success')) {
            var why = isObject(seo) ? str(seo.message) : '';

            found.push(failed('a11y', 'a11y_failed', 'a11y', why, texts));
            found.push(failed('seo', 'seo_failed', 'seo', why, texts));
        } else if (isObject(seo)) {
            found = found.concat(fromA11y(seo.a11y), fromSeo(seo));
        }

        if (s.links === false) {
            found.push(failed('links', 'link_failed', 'links', '', texts));
        } else {
            found = found.concat(fromLinks(s.links, { unreached: !!s.unreached }, texts));
        }

        if (s.leftovers === false) {
            found.push(failed('component', 'component_failed', 'content', '', texts));
        } else {
            found = found.concat(fromLeftovers(s.leftovers, texts));
        }

        return sort(merge(found, isObject(seo) ? seo.concepts : null));
    }

    // A page's result: state 'block' (n blocking), 'look' (n to look at) or
    // 'ready'; a check that failed is counted apart and changes no state.
    function summary(found) {
        var out = { state: 'ready', block: 0, look: 0, failed: 0, groups: {} };

        GROUPS.forEach(function (g) { out.groups[g] = 0; });
        list(found).filter(isObject).forEach(function (f) {
            if (f.level === 'block' || f.level === 'look' || f.level === 'failed') {
                out[f.level]++;
            }

            if (Object.prototype.hasOwnProperty.call(out.groups, f.group)) {
                out.groups[f.group]++;
            }
        });
        out.state = out.block ? 'block' : (out.look ? 'look' : 'ready');

        return out;
    }

    return {
        groups: function () { return GROUPS.slice(); },
        blocking: function () { return BLOCKING.slice(); },
        isBlocking: function (f) { return !!f && blocks(str(f.source), str(f.code)); },
        fromAudit: fromAudit,
        fromSeo: fromSeo,
        fromA11y: fromA11y,
        fromLinks: fromLinks,
        fromLeftovers: fromLeftovers,
        read: read,
        summary: summary
    };
}());
