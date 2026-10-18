(() => {
    'use strict';

    const root = document.querySelector('[data-backup-jobs]');
    const form = document.querySelector('#backup-job-form');

    if (!root || !form) {
        return;
    }

    const api = root.dataset.jobApi || 'job.php';
    const csrfToken = root.dataset.csrfToken || '';
    const stateNode = root.querySelector('[data-job-state]');
    const summaryNode = root.querySelector('[data-job-summary]');
    const databaseNode = root.querySelector('[data-job-databases]');
    const messageNode = root.querySelector('[data-job-message]');
    const resumeButton = root.querySelector('[data-job-resume]');
    const searchInput = form.querySelector('[data-database-search]');
    const selectVisibleButton = form.querySelector('[data-select-visible]');
    const clearSelectionButton = form.querySelector('[data-clear-selection]');
    const selectedCountNode = form.querySelector('[data-selected-count]');
    const databaseOptions = [...form.querySelectorAll('[data-database-option]')];
    const confirmationDialog = document.querySelector('[data-backup-dialog]');
    const confirmationSummary = confirmationDialog?.querySelector('[data-confirm-summary]');
    const confirmationButton = confirmationDialog?.querySelector('[data-confirm-backup]');
    const terminalStates = new Set(['succeeded', 'failed', 'interrupted']);
    let jobId = root.dataset.activeJobId || '';
    let executing = false;
    let timer = null;
    let pendingSubmitter = null;

    const stateLabels = {
        queued: 'queued',
        running: 'running',
        succeeded: 'succeeded',
        failed: 'failed',
        interrupted: 'interrupted'
    };

    function setMessage(message) {
        messageNode.textContent = message || '';
    }

    function setFormDisabled(disabled) {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = disabled;
        });
    }

    function selectedDatabases() {
        return [...form.querySelectorAll('input[name="databases[]"]:checked')];
    }

    function updateSelectedCount() {
        if (selectedCountNode) selectedCountNode.textContent = String(selectedDatabases().length);
    }

    function filterDatabases() {
        const query = (searchInput?.value || '').trim().toLocaleLowerCase();
        databaseOptions.forEach((option) => {
            option.hidden = query !== '' && !(option.dataset.databaseName || '').includes(query);
        });
    }

    function render(job) {
        jobId = job.id;
        root.dataset.activeJobId = job.id;
        stateNode.textContent = stateLabels[job.status] || job.status;
        stateNode.dataset.state = job.status;
        const summary = job.summary || {};
        summaryNode.textContent = [
            `queued ${summary.queued || 0}`,
            `running ${summary.running || 0}`,
            `succeeded ${summary.succeeded || 0}`,
            `failed ${summary.failed || 0}`,
            `interrupted ${summary.interrupted || 0}`,
            `total ${summary.total || 0}`
        ].join(' · ');
        databaseNode.replaceChildren();

        (job.databases || []).forEach((database) => {
            const item = document.createElement('li');
            const name = document.createElement('strong');
            const status = document.createElement('span');
            name.textContent = database.name;
            status.textContent = stateLabels[database.status] || database.status;
            status.dataset.state = database.status;
            item.append(name, status);

            if (database.error && database.error.message) {
                const error = document.createElement('small');
                error.textContent = database.error.message;
                item.append(error);
            }

            databaseNode.append(item);
        });

        const terminal = terminalStates.has(job.status);
        setFormDisabled(!terminal);
        resumeButton.hidden = !job.can_execute;

        if (terminal) {
            executing = false;
            clearTimeout(timer);
        }
    }

    async function requestStatus() {
        if (!jobId) {
            return;
        }

        try {
            const response = await fetch(`${api}?action=status&job_id=${encodeURIComponent(jobId)}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {'X-CSRF-Token': csrfToken}
            });
            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'ไม่สามารถอ่านสถานะงานได้');
            }

            render(payload.job);

            if (!terminalStates.has(payload.job.status)) {
                timer = setTimeout(requestStatus, 1500);
            }
        } catch (error) {
            setMessage(error instanceof Error ? error.message : 'ไม่สามารถอ่านสถานะงานได้');
            timer = setTimeout(requestStatus, 3000);
        }
    }

    function executeJob() {
        if (!jobId || executing) {
            return;
        }

        executing = true;
        resumeButton.hidden = true;
        const body = new FormData();
        body.set('action', 'execute');
        body.set('job_id', jobId);
        body.set('csrf_token', csrfToken);
        void fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            body
        }).then(async (response) => {
            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'ไม่สามารถเริ่มงานสำรองได้');
            }

            render(payload.job);
        }).catch((error) => {
            setMessage(error instanceof Error ? error.message : 'การเชื่อมต่อกับตัวประมวลผลงานสิ้นสุด');
        }).finally(() => {
            executing = false;
            void requestStatus();
        });
        void requestStatus();
    }

    async function createJob(submitter) {
        setMessage('');
        const body = new FormData(form);
        body.set('action', 'create');
        body.set('all', submitter && submitter.dataset.jobMode === 'all' ? '1' : '0');
        setFormDisabled(true);

        try {
            const response = await fetch(api, {method: 'POST', credentials: 'same-origin', body});
            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'ไม่สามารถสร้างงานสำรองได้');
            }

            render(payload.job);
            executeJob();
        } catch (error) {
            setFormDisabled(false);
            setMessage(error instanceof Error ? error.message : 'ไม่สามารถสร้างงานสำรองได้');
        }
    }

    form.addEventListener('change', updateSelectedCount);
    searchInput?.addEventListener('input', filterDatabases);
    selectVisibleButton?.addEventListener('click', () => {
        databaseOptions.filter((option) => !option.hidden).forEach((option) => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = true;
        });
        updateSelectedCount();
    });
    clearSelectionButton?.addEventListener('click', () => {
        databaseOptions.forEach((option) => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        });
        updateSelectedCount();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        pendingSubmitter = event.submitter;
        const all = pendingSubmitter?.dataset.jobMode === 'all';
        const count = all ? databaseOptions.length : selectedDatabases().length;

        if (!all && count === 0) {
            setMessage('กรุณาเลือกฐานข้อมูลอย่างน้อย 1 ฐาน');
            return;
        }

        if (confirmationSummary) {
            confirmationSummary.textContent = all
                ? `สำรองฐานข้อมูลที่ได้รับอนุญาตทั้งหมด ${count} ฐาน`
                : `สำรองฐานข้อมูลที่เลือก ${count} ฐาน`;
        }

        if (confirmationDialog?.showModal) {
            confirmationDialog.showModal();
        } else if (window.confirm(`ยืนยันการสำรอง ${count} ฐานแบบทีละฐาน`)) {
            void createJob(pendingSubmitter);
        }
    });

    confirmationButton?.addEventListener('click', () => {
        confirmationDialog.close();
        void createJob(pendingSubmitter);
    });

    resumeButton.addEventListener('click', executeJob);

    if (jobId) {
        setFormDisabled(true);
        void requestStatus();
    }

    updateSelectedCount();
})();
