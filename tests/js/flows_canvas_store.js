/**
 * Pinegrap - Enterprise Website Platform
 *
 * The api.php of the Flows canvas harness (tests/js/flows_canvas.js): the
 * panel actions the canvas sends (includes/workspace/actions.php), answered
 * in their shapes from flows, runs and a team held in memory, one fresh
 * store per scenario. A scenario reads the calls the page made, changes
 * what the store holds (a draft saved meanwhile elsewhere, a run that moved
 * on), answers the next call of an action itself, or holds an action's
 * answers back until it releases them.
 *
 * The store checks what the server checks where the canvas depends on it:
 * the form token, the draft a save was made on (a 409 field
 * changed_meanwhile), a start token sent as start_token. What stops a
 * draft from being published is a small rule of its own: a flow needs a
 * start box, a task box a title.
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

// What workspace.js's boot answer carries that the canvas reads.
const BOOT = {
    people: [
        { id: 1, name: 'Ayşe Demir', initials: 'AD', tone: 1 },
        { id: 2, name: 'Mehmet Kaya', initials: 'MK', tone: 2 },
        { id: 3, name: 'Zeynep Arslan', initials: 'ZA', tone: 3 }
    ],
    departments: [{ id: 4, name: 'Sales' }],
    channels: [
        { id: 10, name: 'general', kind: 'public', archived: false },
        { id: 11, name: 'sales', kind: 'public', archived: false }
    ],
    priorities: { low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' }
};

// ws_flow_js_config() of staff who build flows, with nothing to choose from
// in the step boxes and no templates.
function flowsConfig() {
    return {
        nav: true,
        scheduled: false,
        ready: true,
        build: true,
        templates: [],
        template_categories: {},
        steps: {
            metrics: [],
            digest_only: {},
            targets: [],
            changes: {},
            records: {},
            operators: {},
            counts: [],
            repeats: {},
            events: null,
            tables: [],
            mcp: [],
            retry: { kinds: [], max: 7, after_max: 10080 }
        },
        outputs: {},
        trigger_outputs: { kinds: {}, events: {} },
        subjects: [],
        derived: {},
        limits: { nodes: 100, notes: 200, coord: 100000, constants: 20 }
    };
}

function clone(value) {
    return (value === undefined) ? undefined : JSON.parse(JSON.stringify(value));
}

function start(x, y) {
    return { id: 'manual_1', kind: 'manual', label: '', x: x, y: y, config: { subject: '' } };
}

function task(id, x, y, title, extra) {
    return {
        id: id,
        kind: 'task',
        label: title,
        x: x,
        y: y,
        config: Object.assign({ title: title, description: '', assignees: [], assign: 'creator', due_in: '', priority: 'normal', timeout: '' }, extra || {})
    };
}

function end(x, y) {
    return { id: 'end_1', kind: 'end', label: '', x: x, y: y, config: {} };
}

// A start, a task and an end in a row.
function line(title) {
    return {
        v: 1,
        nodes: [start(0, 0), task('task_1', 320, 0, title), end(640, 0)],
        edges: [{ from: 'manual_1', to: 'task_1', on: 'next' }, { from: 'task_1', to: 'end_1', on: 'done' }],
        notes: []
    };
}

// The flows of a fixture: 7 the person's draft (opened first), 8 their live
// flow with two runs, 9 a colleague's live flow shared with them at run.
// untitled: 7's second task has no title (a problem); big: 7's draft is
// over the 60 000 bytes a keepalive save may carry; empty: no flow at all.
function fixture(name) {
    const onboarding = {
        v: 1,
        nodes: [
            start(0, 0),
            task('task_1', 320, 0, 'Call the customer', { assignees: [2], assign: '', due_in: 2 }),
            end(640, 0),
            task('task_2', 320, 176, (name === 'untitled') ? '' : 'Send the welcome pack')
        ],
        edges: [{ from: 'manual_1', to: 'task_1', on: 'next' }, { from: 'task_1', to: 'end_1', on: 'done' }],
        notes: []
    };

    if (name === 'big') {
        for (let n = 1; n <= 9; n++) {
            onboarding.notes.push({ id: 'note_' + n, x: 0, y: 320 + n * 16, text: 'Kept as it came. '.repeat(470) });
        }
    }

    const report = line('Write the report');
    const flows = [
        { id: 7, name: 'Customer onboarding', status: 'draft', access: 'edit', owner_id: ME, channel_id: 0, draft_rev: 3, version: 0, draft: onboarding, live: null },
        { id: 8, name: 'Weekly report', status: 'live', access: 'edit', owner_id: ME, channel_id: 10, draft_rev: 5, version: 2, draft: report, live: clone(report) },
        { id: 9, name: 'Stock check', status: 'live', access: 'run', owner_id: 2, channel_id: 11, draft_rev: 2, version: 1, draft: line('Count the shelves'), live: line('Count the shelves') }
    ];
    const runs = [
        {
            id: 40, flow_id: 8, version: 2, status: 'done', started_at: '2026-10-28T09:00:00Z', finished_at: '2026-10-28T11:30:00Z', result: 'Write the report: done',
            steps: [
                { node_id: 'manual_1', status: 'done', outcome: 'next', task_id: null, started_at: '2026-10-28T09:00:00Z', finished_at: '2026-10-28T09:00:00Z', result: '' },
                { node_id: 'task_1', status: 'done', outcome: 'done', task_id: 500, started_at: '2026-10-28T09:00:01Z', finished_at: '2026-10-28T11:30:00Z', result: 'Done by Mehmet Kaya' },
                { node_id: 'end_1', status: 'done', outcome: null, task_id: null, started_at: '2026-10-28T11:30:00Z', finished_at: '2026-10-28T11:30:00Z', result: '' }
            ]
        },
        {
            id: 41, flow_id: 8, version: 2, status: 'waiting', started_at: '2026-10-29T09:00:00Z', finished_at: null, result: 'Waiting for Write the report',
            steps: [
                { node_id: 'manual_1', status: 'done', outcome: 'next', task_id: null, started_at: '2026-10-29T09:00:00Z', finished_at: '2026-10-29T09:00:00Z', result: '' },
                { node_id: 'task_1', status: 'waiting', outcome: null, task_id: 501, started_at: '2026-10-29T09:00:01Z', finished_at: null, result: '' }
            ]
        }
    ];

    return { flows: (name === 'empty') ? [] : flows, runs: (name === 'empty') ? [] : runs };
}

// What stops a draft from being published, in the shape of
// ws_flow_problems(): no start box, a task box without a title.
function problemsOf(graph) {
    const problems = [];

    if (!graph.nodes.some((node) => node.kind === 'manual')) {
        problems.push({ code: 'no_start', node: null, edge: null, text: 'Add a box that starts the flow.' });
    }

    graph.nodes.forEach((node) => {
        if ((node.kind === 'task') && !String((node.config || {}).title || '').trim()) {
            problems.push({ code: 'task_title', node: node.id, edge: null, text: 'Give the task a title.' });
        }
    });

    return problems;
}

// The store of one scenario.
function createStore(name) {
    const data = fixture(name || 'draft');
    const flows = new Map(data.flows.map((flow) => [flow.id, flow]));
    const runs = new Map(data.runs.map((run) => [run.id, run]));
    const calls = [];
    const unexpected = [];
    const next = {};
    const held = {};
    let nextFlow = 20;
    let nextRun = 42;

    const refuse = (message, field) => ({ refuse: { message: message, field: field || '' } });

    function runOf(run) {
        const flow = flows.get(run.flow_id);

        return Object.assign({ version_id: run.flow_id * 10 + run.version, access: flow.access, can_cancel: (run.status === 'running') || (run.status === 'waiting'), started_by: ME, actor_id: ME, channel_id: null, boxes_run: run.steps.length, updated_at: run.started_at }, clone(run), {
            steps: run.steps.map((step) => Object.assign({ kind: (flow.live.nodes.filter((node) => node.id === step.node_id)[0] || {}).kind || '', name: step.node_id, attempt: 1, ready_at: step.started_at, threads_in: [], outputs: {} }, clone(step))),
            graph: clone(flow.live)
        });
    }

    function runBrief(run) {
        return Object.assign(runOf(run), { steps: null, graph: null });
    }

    function lastRun(flow) {
        const own = [...runs.values()].filter((run) => run.flow_id === flow.id).sort((a, b) => b.id - a.id);

        return own.length ? runBrief(own[0]) : null;
    }

    function brief(flow) {
        return {
            id: flow.id,
            name: flow.name,
            status: flow.status,
            access: flow.access,
            mine: flow.owner_id === ME,
            channel_id: flow.channel_id,
            draft_rev: flow.draft_rev,
            version: flow.version,
            start: 'manual',
            last_run: lastRun(flow)
        };
    }

    function detail(flow) {
        const edit = flow.access === 'edit';
        const draft = (edit || !flow.version) ? flow.draft : null;

        return Object.assign(brief(flow), {
            draft: clone(draft),
            graph: flow.version ? clone(flow.live) : null,
            problems: (edit && draft) ? problemsOf(draft) : [],
            warnings: [],
            changed: edit && (!flow.version || (JSON.stringify(flow.draft) !== JSON.stringify(flow.live))),
            manage: flow.owner_id === ME,
            owner_id: flow.owner_id,
            ai_marks: null
        });
    }

    function flowOf(request, edit) {
        const flow = flows.get(Number(request.flow_id));

        if (!flow || flow.binned) {
            return refuse('That flow could not be found.');
        }

        return (edit && (flow.access !== 'edit')) ? refuse('Only the owner of a flow can change it.') : flow;
    }

    const actions = {
        ws_flow_list: () => ({ flows: [...flows.values()].filter((flow) => !flow.binned).map(brief) }),

        ws_flow_get: (request) => {
            const flow = flowOf(request, false);

            return flow.refuse ? flow : { flow: detail(flow) };
        },

        ws_flow_create: (request) => {
            const flow = { id: nextFlow++, name: String(request.name || ''), status: 'draft', access: 'edit', owner_id: ME, channel_id: 0, draft_rev: 1, version: 0, draft: clone(request.graph), live: null };

            flows.set(flow.id, flow);

            return { flow: detail(flow) };
        },

        ws_flow_save: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            if (Number(request.draft_rev) !== flow.draft_rev) {
                return refuse('This flow was changed on another screen meanwhile.', 'changed_meanwhile');
            }

            flow.draft = clone(request.graph);
            flow.name = (typeof request.name === 'string') ? request.name : flow.name;
            flow.channel_id = Number(request.channel_id) || 0;
            flow.draft_rev++;

            return { draft_rev: flow.draft_rev, problems: problemsOf(flow.draft), warnings: [] };
        },

        ws_flow_publish: (request) => {
            const flow = flowOf(request, true);

            if (flow.refuse) {
                return flow;
            }

            if (Number(request.draft_rev) !== flow.draft_rev) {
                return refuse('This flow was changed on another screen meanwhile.', 'changed_meanwhile');
            }

            flow.version++;
            flow.status = 'live';
            flow.live = clone(flow.draft);

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

        ws_flow_runs_list: (request) => {
            const flow = flowOf(request, false);

            return flow.refuse ? flow : { runs: [...runs.values()].filter((run) => run.flow_id === flow.id).sort((a, b) => b.id - a.id).map(runBrief), next: null };
        },

        ws_flow_run_get: (request) => {
            const run = runs.get(Number(request.run_id));

            return run ? { run: runOf(run), due: false } : refuse('That run could not be found.');
        },

        ws_flow_run_start: (request) => {
            const flow = flowOf(request, false);

            if (flow.refuse) {
                return flow;
            }

            if (!/^[A-Za-z0-9._:-]{1,56}$/.test(String(request.start_token || ''))) {
                return refuse('The start could not be read.');
            }

            const run = {
                id: nextRun++, flow_id: flow.id, version: flow.version, status: 'running', started_at: '2026-10-29T12:00:00Z', finished_at: null, result: '',
                steps: [{ node_id: 'manual_1', status: 'done', outcome: 'next', task_id: null, started_at: '2026-10-29T12:00:00Z', finished_at: '2026-10-29T12:00:00Z', result: '' }]
            };

            runs.set(run.id, run);

            return { started: true, run: runOf(run) };
        },

        ws_flow_run_cancel: (request) => {
            const run = runs.get(Number(request.run_id));

            if (!run) {
                return refuse('That run could not be found.');
            }

            run.status = 'cancelled';
            run.finished_at = '2026-10-29T12:30:00Z';

            return { run: runOf(run) };
        },

        ws_tick: () => ({})
    };

    // The answer to one request: { status, body } as JSON, or drop: true
    // for a request that gets no answer at all.
    function answer(request) {
        const action = String(request.action || '');

        if (request.token !== TOKEN) {
            return { status: 200, body: { status: 'error', message: 'The page has expired. Load it again.', field: '' } };
        }

        let result;

        if (next[action] && next[action].length) {
            result = next[action].shift()(request, store);
        } else if (actions[action]) {
            result = actions[action](request);
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
        token: TOKEN,
        boot: BOOT,
        flows: flows,
        runs: runs,
        calls: calls,
        unexpected: unexpected,
        config: flowsConfig,
        refuse: refuse,

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

        // A request the page sent: recorded at once, answered now or once
        // its action is released.
        receive: (request, keepalive, send) => {
            const data = Object.assign({}, request);

            delete data.action;
            delete data.token;
            calls.push({ action: String(request.action || ''), data: data, keepalive: !!keepalive, token: request.token });

            const reply = () => send(answer(request));

            if (held[request.action]) {
                held[request.action].push(reply);
            } else {
                reply();
            }
        }
    };

    return store;
}

module.exports = { createStore: createStore, ME: ME, TOKEN: TOKEN };
