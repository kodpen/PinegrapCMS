/**
 * Pinegrap - Enterprise Website Platform
 *
 * The api.php of the Flows canvas harness (tests/js/flows_canvas.js): the
 * panel actions the flows screen sends (includes/workspace/actions.php),
 * answered in their shapes from flows, runs and a team held in memory, one
 * fresh store per scenario. A scenario reads the calls the page made,
 * changes what the store holds (a draft saved meanwhile elsewhere, a run
 * that moved on), answers the next call of an action itself, or holds an
 * action's answers back until it releases them.
 *
 * A draft is kept as the server keeps it: every graph that comes in goes
 * through the product's ws_flow_graph_normalize() and ws_flow_graph_check()
 * (flows_canvas_server.php, the check passed in), so the problems and
 * warnings the canvas shows are the server's. The store checks what the
 * server checks where the canvas depends on it, with the server's words:
 * the form token, who may change and publish a flow, a name, the draft a
 * save or a publish was made on (409, field changed_meanwhile), a draft
 * with problems refused at publish, the start token of a run (the same
 * token answers the run it started).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

// The person the page is open for and the form token api.php checks.
const ME = 1;
const TOKEN = 'harness-form-token';

const TEAM = [
    { id: 1, name: 'Ayşe Demir', initials: 'AD', tone: 1 },
    { id: 2, name: 'Mehmet Kaya', initials: 'MK', tone: 2 },
    { id: 3, name: 'Zeynep Arslan', initials: 'ZA', tone: 3 }
];

// What ws_bootstrap answers, as far as the flows screen reads it.
function boot() {
    const people = TEAM.map((person) => Object.assign({ username: person.name.split(' ')[0].toLowerCase(), role: (person.id === ME) ? 0 : 2, title: '', avatar: '', avatar_kind: '', presence: 'offline' }, person));

    return {
        me: Object.assign({}, people[0], { rights: { assign: 1, board: 1, settings: 1, staff: true, leads: [] } }),
        people: people,
        departments: [{ id: 4, name: 'Sales' }],
        channels: [
            { id: 10, name: 'general', kind: 'public', archived: false },
            { id: 11, name: 'sales', kind: 'public', archived: false }
        ],
        groups: [],
        threads: [],
        inbox: 0,
        statuses: {},
        priorities: { low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' },
        ref_types: [],
        quick_edit: [],
        commands: [],
        command_values: {},
        upload: { max: 10485760, accept: '' },
        interact: false,
        reactions: [],
        claude: null,
        ai: null,
        mcp: [],
        now: Math.floor(Date.now() / 1000)
    };
}

function clone(value) {
    return (value === undefined) ? undefined : JSON.parse(JSON.stringify(value));
}

function box(id, kind, x, y, label, config) {
    return { id: id, kind: kind, label: label || '', x: x, y: y, config: config || {} };
}

function task(id, x, y, title, extra) {
    return box(id, 'task', x, y, title, Object.assign({ title: title, assign: 'creator' }, extra || {}));
}

// A start, a task and an end in a row.
function line(title) {
    return {
        v: 1,
        nodes: [box('manual_1', 'manual', 0, 0), task('task_1', 320, 0, title), box('end_1', 'end', 640, 0)],
        edges: [{ from: 'manual_1', to: 'task_1', on: 'next' }, { from: 'task_1', to: 'end_1', on: 'done' }],
        notes: []
    };
}

// The fixtures. draft (the default): 7 the person's draft, opened first: a
// start, a task and an end in a row and a second task no arrow leads to
// (the server's one problem); 8 their live flow at version 2 with two runs,
// one done and one waiting for its task; 9 a colleague's live flow shared
// with them at run. problems: 7's second task has no title either, and a
// second arrow joins the task to the end on the same outcome. threads: 7's
// boxes in one row, its second task reading the first one's task in its
// description (a data thread). big: 7's draft holds notes enough to be over
// the 60 000 bytes a keepalive save may carry. empty: no flow at all.
function fixture(name) {
    const onboarding = {
        v: 1,
        nodes: [
            box('manual_1', 'manual', 0, 0),
            task('task_1', 320, 0, 'Call the customer', { assignees: [2], assign: '', due_in: 2 }),
            box('end_1', 'end', 640, 0),
            task('task_2', 320, 176, (name === 'problems') ? '' : 'Send the welcome pack')
        ],
        edges: [{ from: 'manual_1', to: 'task_1', on: 'next' }, { from: 'task_1', to: 'end_1', on: 'done' }],
        notes: []
    };

    if (name === 'problems') {
        onboarding.edges.push({ from: 'task_1', to: 'end_1', on: 'done' });
    }

    if (name === 'threads') {
        onboarding.nodes = [
            box('manual_1', 'manual', 0, 0),
            task('task_1', 320, 0, 'Call the customer', { assignees: [2], assign: '' }),
            task('task_2', 640, 0, 'Send the welcome pack', { description: 'After task {{node.task_1.task_id}}' }),
            box('end_1', 'end', 960, 0)
        ];
        onboarding.edges = [{ from: 'manual_1', to: 'task_1', on: 'next' }, { from: 'task_1', to: 'task_2', on: 'done' }, { from: 'task_2', to: 'end_1', on: 'done' }];
    }

    if (name === 'big') {
        for (let n = 1; n <= 32; n++) {
            onboarding.notes.push({ id: 'note_' + n, x: 0, y: 400 + n * 16, w: 0, h: 0, text: 'Kept as it came. '.repeat(117) });
        }
    }

    const report = line('Write the report');
    const flows = [
        { id: 7, name: 'Customer onboarding', status: 'draft', access: 'edit', owner_id: ME, channel_id: 0, draft_rev: 3, version: 0, draft: onboarding, live: null },
        { id: 8, name: 'Weekly report', status: 'live', access: 'edit', owner_id: ME, channel_id: 10, draft_rev: 5, version: 2, draft: report, live: clone(report) },
        { id: 9, name: 'Stock check', status: 'live', access: 'run', owner_id: 2, channel_id: 11, draft_rev: 2, version: 1, draft: line('Count the shelves'), live: line('Count the shelves') }
    ];
    const step = (node, status, outcome, at, end, result, task) => ({ node_id: node, status: status, outcome: outcome || null, task_id: task || null, started_at: at, finished_at: end || null, result: result || '' });
    const runs = [
        {
            id: 40, flow_id: 8, version: 2, status: 'done', started_by: ME, started_at: '2026-10-28T09:00:00Z', finished_at: '2026-10-28T11:30:00Z', result: 'Write the report: done',
            steps: [
                step('manual_1', 'done', 'next', '2026-10-28T09:00:00Z', '2026-10-28T09:00:00Z'),
                step('task_1', 'done', 'done', '2026-10-28T09:00:01Z', '2026-10-28T11:30:00Z', 'Done by Mehmet Kaya', 500),
                step('end_1', 'done', null, '2026-10-28T11:30:00Z', '2026-10-28T11:30:00Z')
            ]
        },
        {
            id: 41, flow_id: 8, version: 2, status: 'waiting', started_by: ME, started_at: '2026-10-29T09:00:00Z', finished_at: null, result: 'Waiting for Write the report',
            steps: [
                step('manual_1', 'done', 'next', '2026-10-29T09:00:00Z', '2026-10-29T09:00:00Z'),
                step('task_1', 'waiting', null, '2026-10-29T09:00:01Z', null, '', 501)
            ]
        }
    ];

    return { flows: (name === 'empty') ? [] : flows, runs: (name === 'empty') ? [] : runs };
}

// The store of one scenario. check(graph) gives a promise of { graph,
// problems, warnings } (flows_canvas_server.php).
async function createStore(name, check) {
    const data = fixture(name || 'draft');
    const flows = new Map();
    const runs = new Map(data.runs.map((run) => [run.id, run]));
    const tokens = new Map();
    const calls = [];
    const unexpected = [];
    const next = {};
    const held = {};
    let nextFlow = 20;
    let nextRun = 42;
    let queue = Promise.resolve();

    // A draft as the server keeps it: normalized, with its problems and
    // warnings.
    async function keep(flow, graph) {
        const checked = await check(graph);

        flow.draft = checked.graph;
        flow.problems = checked.problems;
        flow.warnings = checked.warnings;

        return checked;
    }

    // versions: each published version's graph, by its number.
    for (const flow of data.flows) {
        const live = flow.live ? (await check(flow.live)).graph : null;

        await keep(flow, flow.draft);
        flow.live = live;
        flow.versions = live ? { [flow.version]: live } : {};
        flows.set(flow.id, flow);
    }

    const refuse = (message, field) => ({ refuse: { message: message, field: field || '' } });
    const changedMeanwhile = () => refuse('This flow was changed meanwhile, on another screen or device. Load it again.', 'changed_meanwhile');
    const now = () => new Date().toISOString().replace(/\.\d+Z$/, 'Z');
    const open = (run) => (run.status === 'running') || (run.status === 'waiting');

    // A run in the shape of ws_api_flow_run_present(); full: its boxes and
    // the graph of its version too.
    function present(run, full) {
        const flow = flows.get(run.flow_id);
        const access = (flow.access === 'edit') ? 'edit' : 'read';
        const graph = flow.versions[run.version] || flow.live;
        const nodeOf = (id) => graph.nodes.filter((node) => node.id === id)[0] || null;

        return {
            id: run.id,
            flow_id: run.flow_id,
            version_id: run.flow_id * 100 + run.version,
            version: run.version,
            status: run.status,
            access: access,
            can_cancel: open(run) && ((access === 'edit') || (run.started_by === ME)),
            started_by: run.started_by,
            actor_id: run.started_by,
            channel_id: null,
            boxes_run: run.steps.length,
            started_at: run.started_at,
            updated_at: run.finished_at || run.started_at,
            finished_at: run.finished_at,
            result: run.result || '',
            steps: full ? run.steps.map((step) => {
                const node = nodeOf(step.node_id);

                return {
                    node_id: step.node_id,
                    kind: node ? node.kind : '',
                    name: node ? (node.label || node.id) : step.node_id,
                    status: step.status,
                    outcome: step.outcome,
                    task_id: step.task_id,
                    attempt: 1,
                    ready_at: step.started_at,
                    started_at: step.started_at,
                    finished_at: step.finished_at,
                    threads_in: [],
                    outputs: {},
                    result: step.result
                };
            }) : null,
            graph: full ? clone(graph) : null
        };
    }

    function lastRun(flow) {
        const own = [...runs.values()].filter((run) => run.flow_id === flow.id).sort((a, b) => b.id - a.id);

        return own.length ? present(own[0], false) : null;
    }

    // ws_flow_brief().
    function brief(flow) {
        const start = (flow.live || flow.draft).nodes.filter((node) => ['manual', 'schedule', 'event', 'join', 'row'].indexOf(node.kind) !== -1)[0];

        return {
            id: flow.id,
            name: flow.name,
            status: flow.status,
            access: flow.access,
            mine: flow.owner_id === ME,
            channel_id: flow.channel_id,
            draft_rev: flow.draft_rev,
            version: flow.version,
            start: start ? start.kind : ''
        };
    }

    // ws_flow_detail(): the draft for whoever may change the flow (or for
    // anyone of a flow never published), the live graph for the others.
    function detail(flow) {
        const edit = flow.access === 'edit';
        const draft = (edit || !flow.version) ? flow.draft : null;

        return Object.assign(brief(flow), {
            draft: clone(draft),
            graph: flow.version ? clone(flow.live) : null,
            problems: (edit && draft) ? clone(flow.problems) : [],
            warnings: (edit && draft) ? clone(flow.warnings) : [],
            changed: edit && !!draft && (!flow.live || (JSON.stringify(flow.draft) !== JSON.stringify(flow.live))),
            manage: flow.owner_id === ME,
            owner_id: flow.owner_id,
            ai_marks: null
        });
    }

    const templates = new Map();
    let templateId = 0;

    const savedEntry = (template) => ({
        key: 'site_' + template.id,
        category: 'saved',
        title: template.title,
        help: template.help,
        icon: 'bi-bookmark-star',
        start: template.graph.nodes[0].kind,
        graph: template.graph,
        boxes: template.graph.nodes.length,
        threads: 0,
        settings: [],
        motion: { boxes: [], edges: [], threads: [], panel: 0 },
        saved: { id: template.id, category: template.category, created_by: ME, source: 0, updated_at: 0, edit: true }
    });

    function flowOf(request, edit) {
        const flow = flows.get(Number(request.flow_id));

        if (!flow || flow.binned) {
            return refuse(edit ? 'Only the owner of a flow can change it.' : 'That flow could not be found.');
        }

        return (edit && (flow.access !== 'edit')) ? refuse('Only the owner of a flow can change it.') : flow;
    }

    const actions = {
        ws_bootstrap: () => boot(),

        ws_tick: () => ({}),

        ws_flow_list: () => ({ flows: [...flows.values()].filter((flow) => !flow.binned).map((flow) => Object.assign(brief(flow), { last_run: lastRun(flow) })) }),

        ws_flow_get: (request) => {
            const flow = flowOf(request, false);

            return flow.refuse ? flow : { flow: detail(flow) };
        },

        ws_flow_create: async (request) => {
            const name = String(request.name || '').trim();

            if (!name) {
                return refuse('A flow needs a name.', 'name');
            }

            const flow = { id: nextFlow++, name: name, status: 'draft', access: 'edit', owner_id: ME, channel_id: Number(request.channel_id) || 0, draft_rev: 1, version: 0, live: null, versions: {} };

            await keep(flow, request.graph || {});
            flows.set(flow.id, flow);

            return { flow: detail(flow) };
        },

        ws_flow_save: async (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            if (Number(request.draft_rev) !== flow.draft_rev) {
                return changedMeanwhile();
            }

            if (('name' in request) && !String(request.name || '').trim()) {
                return refuse('A flow needs a name.', 'name');
            }

            if (('channel_id' in request) && ((Number(request.channel_id) || 0) !== flow.channel_id) && (flow.owner_id !== ME)) {
                return refuse('Only the owner of a flow can move it to another home channel.', 'channel_id');
            }

            const checked = ('graph' in request) ? await keep(flow, request.graph) : { problems: flow.problems, warnings: flow.warnings };

            if ('name' in request) {
                flow.name = String(request.name).trim();
            }

            if ('channel_id' in request) {
                flow.channel_id = Number(request.channel_id) || 0;
            }

            flow.draft_rev++;

            return { draft_rev: flow.draft_rev, problems: clone(checked.problems), warnings: clone(checked.warnings) };
        },

        ws_flow_publish: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            if (flow.owner_id !== ME) {
                return refuse('Only the owner of a flow can publish it.');
            }

            if (('draft_rev' in request) && (Number(request.draft_rev) !== flow.draft_rev)) {
                return changedMeanwhile();
            }

            if (flow.problems.length) {
                return refuse('Fix the flow before it is published: ' + flow.problems[0].text);
            }

            flow.version++;
            flow.status = 'live';
            flow.live = clone(flow.draft);
            flow.versions[flow.version] = flow.live;

            return { flow: detail(flow) };
        },

        ws_flow_pause: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            flow.status = 'paused';

            return { flow: detail(flow) };
        },

        ws_flow_bin: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            flow.binned = true;

            return {};
        },

        // Templates saved on the site (flow_templates_saved.php): the
        // flow's draft kept under site_<n>, its title, line and group
        // changed, binned; the answer in the gallery's shape.
        ws_flow_template_save: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            const title = String(request.title || '').trim();

            if (!title) {
                return refuse('Give the template a title.', 'title');
            }

            const graph = clone(flow.draft);
            const template = { id: ++templateId, title: title, help: String(request.help || ''), category: String(request.category || 'team'), graph: graph };

            templates.set(template.id, template);

            return { template: savedEntry(template) };
        },

        ws_flow_template_update: (request) => {
            const template = templates.get(Number(String(request.key || '').replace('site_', '')));

            if (!template) {
                return refuse('That template could not be found, or you cannot change it.');
            }

            ['title', 'help', 'category'].forEach((key) => {
                if (request[key] !== undefined) {
                    template[key] = String(request[key]);
                }
            });

            return { template: savedEntry(template) };
        },

        ws_flow_template_bin: (request) => {
            const id = Number(String(request.key || '').replace('site_', ''));

            if (!templates.has(id)) {
                return refuse('That template could not be found, or you cannot change it.');
            }

            templates.delete(id);

            return {};
        },

        ws_flow_shares: (request) => {
            const flow = flowOf(request, false);

            return flow.refuse ? flow : { shares: [], manage: flow.owner_id === ME, owner_id: flow.owner_id };
        },

        ws_flow_runs_list: (request) => {
            const flow = flowOf(request, false);

            return flow.refuse ? flow : { runs: [...runs.values()].filter((run) => run.flow_id === flow.id).sort((a, b) => b.id - a.id).map((run) => present(run, false)), next: null };
        },

        ws_flow_run_get: (request) => {
            const run = runs.get(Number(request.run_id));

            return run ? { run: present(run, true), due: false } : refuse('That run could not be found.');
        },

        // ws_flow_run_start(): a token of the right shape, a live flow; a
        // token seen before answers the run it started (started false).
        ws_flow_run_start: (request) => {
            const flow = flowOf(request, false);
            const token = request.start_token;

            if (flow.refuse) {
                return flow;
            }

            if ((token !== undefined) && !/^[A-Za-z0-9._:-]{1,56}$/.test(String(token))) {
                return refuse('The token of a run is 1-56 letters, digits and . _ : - characters.', 'token');
            }

            if ((flow.status !== 'live') || !flow.version) {
                return refuse('Only a published flow that is live can be run.');
            }

            if (token && tokens.has(flow.id + ' ' + token)) {
                return { run: present(runs.get(tokens.get(flow.id + ' ' + token)), true), started: false };
            }

            const at = now();
            const start = flow.live.nodes.filter((node) => node.kind === 'manual')[0];
            const run = {
                id: nextRun++, flow_id: flow.id, version: flow.version, status: 'running', started_by: ME, started_at: at, finished_at: null, result: '',
                steps: start ? [{ node_id: start.id, status: 'done', outcome: 'next', task_id: null, started_at: at, finished_at: at, result: '' }] : []
            };

            runs.set(run.id, run);

            if (token) {
                tokens.set(flow.id + ' ' + token, run.id);
            }

            return { run: present(run, true), started: true };
        },

        ws_flow_run_cancel: (request) => {
            const run = runs.get(Number(request.run_id));

            if (!run) {
                return refuse('That run could not be found.');
            }

            if (!open(run)) {
                return refuse('This run has ended already.');
            }

            if (!present(run, false).can_cancel) {
                return refuse('Only the owner of the flow or the person who started the run can cancel it.');
            }

            run.status = 'cancelled';
            run.finished_at = now();
            run.steps.forEach((step) => {
                if (open(step)) {
                    step.status = 'skipped';
                }
            });

            return { run: present(run, true) };
        }
    };

    // The answer to one request: { status, body } as JSON, { status, html }
    // for a page that is not JSON, or drop: true for no answer at all.
    async function answer(request) {
        const action = String(request.action || '');
        let result;

        if (request.token !== TOKEN) {
            return { status: 200, body: { status: 'error', message: 'The page has expired. Load it again.', field: '' } };
        }

        if (next[action] && next[action].length) {
            result = await next[action].shift()(request, store);
        } else if (actions[action]) {
            result = await actions[action](request);
        } else {
            unexpected.push(action);
            result = refuse('Unknown action.');
        }

        if (result && result.drop) {
            return { drop: true };
        }

        if (result && result.errorPage) {
            return { status: 500, html: '<!DOCTYPE html><title>Server error</title><p>Internal Server Error</p>' };
        }

        if (result && result.refuse) {
            return { status: 200, body: { status: 'error', message: result.refuse.message, field: result.refuse.field } };
        }

        return { status: 200, body: { status: 'success', data: result || {} } };
    }

    const store = {
        me: ME,
        token: TOKEN,
        flows: flows,
        runs: runs,
        calls: calls,
        unexpected: unexpected,
        refuse: refuse,

        // A draft as another screen saves it: kept as the server keeps it.
        keep: keep,

        // A request answered with nothing (a network failure), and one
        // answered with a server error page that is not JSON.
        drop: () => ({ drop: true }),
        errorPage: () => ({ errorPage: true }),

        // The next call of an action is answered by fn(request, store);
        // base() answers it as the store does.
        next: (action, fn) => {
            (next[action] = next[action] || []).push(fn);
        },

        base: (action, request) => actions[action](request),

        // Answers of an action wait until it is released.
        hold: (action) => {
            held[action] = held[action] || [];
        },

        release: (action) => {
            const waiting = held[action] || [];

            delete held[action];
            waiting.forEach((send) => send());
        },

        // The calls of an action (all of them without one).
        callsOf: (action) => calls.filter((call) => !action || (call.action === action)),

        // A request the page sent: recorded at once, answered in the order
        // the requests came, sent now or once its action is released.
        receive: (request, keepalive, send) => {
            const data = Object.assign({}, request);

            delete data.action;
            delete data.token;
            calls.push({ action: String(request.action || ''), data: data, keepalive: !!keepalive, at: Date.now() });

            queue = queue.then(() => answer(request)).then((reply) => {
                if (held[request.action]) {
                    held[request.action].push(() => send(reply));
                } else {
                    send(reply);
                }
            }, (error) => {
                send({ status: 500, html: '<!DOCTYPE html><title>Server error</title><pre>' + String(error && error.stack || error).replace(/</g, '&lt;') + '</pre>' });
            });
        }
    };

    return store;
}

module.exports = { createStore: createStore, ME: ME, TOKEN: TOKEN };
