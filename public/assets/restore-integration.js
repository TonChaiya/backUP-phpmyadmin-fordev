'use strict';

(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) root.RestoreIntegrationUi = api;
})(typeof window !== 'undefined' ? window : null, function () {
    const states = new Set(['queued', 'validating', 'running', 'succeeded', 'failed', 'interrupted', 'blocked']);
    function canExecuteLive() { return false; }
    function safeStatus(job) { return job && states.has(job.status) ? job.status : 'blocked'; }
    return { canExecuteLive, safeStatus };
});
