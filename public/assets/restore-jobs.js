(() => {
    'use strict';

    const panel = document.querySelector('[data-restore-jobs]');
    const form = document.querySelector('#restore-preflight-form');
    if (!panel || !form) return;

    const api = panel.dataset.jobApi;
    const csrf = panel.dataset.csrfToken;
    const stateNode = panel.querySelector('[data-restore-state]');
    const messageNode = panel.querySelector('[data-restore-message]');
    const findingsNode = panel.querySelector('[data-restore-findings]');
    const resumeButton = panel.querySelector('[data-restore-resume]');
    let jobId = panel.dataset.activeJobId || '';
    let busy = false;
    let pollTimer = null;
    const terminalStates = new Set(['blocked', 'failed', 'interrupted']);

    const request = async (action, data = null) => {
        const options = {headers: {'X-CSRF-Token': csrf, 'Accept': 'application/json'}};
        let url = `${api}?action=${encodeURIComponent(action)}`;
        if (data) {
            options.method = 'POST';
            const body = new FormData();
            body.set('action', action);
            body.set('csrf_token', csrf);
            Object.entries(data).forEach(([key, value]) => body.set(key, value));
            options.body = body;
        } else if (jobId) {
            url += `&job_id=${encodeURIComponent(jobId)}`;
        }
        const response = await fetch(url, options);
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.error || 'Restore preflight request failed');
        return payload.job;
    };

    const render = (job) => {
        jobId = job.id;
        stateNode.textContent = job.status;
        stateNode.dataset.state = job.status;
        messageNode.textContent = job.result?.message
            || (job.status === 'running' ? 'กำลังตรวจสอบไฟล์จริงและโครงสร้าง SQL' : 'รอเริ่มตรวจสอบไฟล์');
        findingsNode.replaceChildren();
        const details = [...(job.result?.findings || [])];
        Object.entries(job.result?.checks || {}).forEach(([name, value]) => details.push(`${name}: ${value}`));
        details.forEach((detail) => {
            const item = document.createElement('li');
            item.textContent = detail;
            findingsNode.append(item);
        });
        resumeButton.hidden = !job.can_preflight;
        form.querySelectorAll('button, input, select').forEach((node) => { node.disabled = ['queued', 'running'].includes(job.status); });
    };

    const requestStatus = async () => {
        if (!jobId) return;
        try {
            const job = await request('status');
            render(job);
            if (!terminalStates.has(job.status)) {
                pollTimer = setTimeout(requestStatus, 1500);
            }
        } catch (error) {
            messageNode.textContent = error.message;
            pollTimer = setTimeout(requestStatus, 3000);
        }
    };

    const preflight = async () => {
        if (!jobId || busy) return;
        busy = true;
        try { render(await request('preflight', {job_id: jobId})); }
        catch (error) { messageNode.textContent = error.message; }
        finally { busy = false; }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        try {
            const data = new FormData(form);
            render(await request('create', {artifact: data.get('artifact'), target: data.get('target')}));
            busy = false;
            await preflight();
        } catch (error) {
            messageNode.textContent = error.message;
        } finally {
            busy = false;
        }
    });
    resumeButton.addEventListener('click', preflight);

    if (jobId) void requestStatus();
})();
