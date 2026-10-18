const fs = require('fs');
const vm = require('vm');

class NodeStub {
    constructor() {
        this.dataset = {};
        this.textContent = '';
        this.hidden = false;
        this.disabled = false;
        this.listeners = {};
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelector() { return null; }
    querySelectorAll() { return []; }
    replaceChildren() {}
    append() {}
}

const stateNode = new NodeStub();
const messageNode = new NodeStub();
const findingsNode = new NodeStub();
const resumeButton = new NodeStub();
const panel = new NodeStub();
panel.dataset = {jobApi: 'restore-job.php', csrfToken: 'token', activeJobId: 'a'.repeat(32)};
panel.querySelector = (selector) => ({
    '[data-restore-state]': stateNode,
    '[data-restore-message]': messageNode,
    '[data-restore-findings]': findingsNode,
    '[data-restore-resume]': resumeButton,
}[selector] || null);

const form = new NodeStub();
const requests = [];
const timers = [];
const runningJob = {
    id: 'a'.repeat(32), status: 'running', can_preflight: false, result: null,
};

const context = {
    FormData: class { set() {} get() { return ''; } },
    console,
    document: {
        querySelector: (selector) => selector === '[data-restore-jobs]' ? panel : (selector === '#restore-preflight-form' ? form : null),
        createElement: () => new NodeStub(),
    },
    fetch: async (url, options = {}) => {
        requests.push({url, method: options.method || 'GET'});
        return {ok: true, json: async () => ({ok: true, job: runningJob})};
    },
    setTimeout: (callback, delay) => { timers.push({callback, delay}); return timers.length; },
    clearTimeout: () => {},
};

vm.runInNewContext(fs.readFileSync('public/assets/restore-jobs.js', 'utf8'), context);

setImmediate(() => {
    if (requests.length !== 1 || !requests[0].url.includes('action=status') || requests[0].method !== 'GET') {
        throw new Error('Reload must issue exactly one initial GET status request');
    }
    if (timers.length !== 1 || timers[0].delay !== 1500) {
        throw new Error('A running restore job must schedule one status-only poll');
    }
    if (stateNode.textContent !== 'running' || !messageNode.textContent.includes('กำลังตรวจสอบ')) {
        throw new Error('Running state must be rendered truthfully');
    }
    process.stdout.write('PASS restore reload polls status without auto-preflight\n');
});
