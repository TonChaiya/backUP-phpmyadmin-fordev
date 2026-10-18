const { spawn } = require('node:child_process');
const fs = require('node:fs');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');

const chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const baseUrl = 'http://localhost:8081/backUP-phpmyadmin-fordev/';
const pages = { dashboard: 2, backup: 3, restore: 3, 'restore-integration': 2, history: 1, settings: 4 };

function assert(condition, message) { if (!condition) throw new Error(message); }

async function freePort() {
    return await new Promise((resolve, reject) => {
        const server = net.createServer();
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const address = server.address();
            server.close(() => resolve(address.port));
        });
    });
}

async function waitForPage(port) {
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            const targets = await fetch(`http://127.0.0.1:${port}/json`).then((response) => response.json());
            const page = targets.find((entry) => entry.type === 'page');
            if (page?.webSocketDebuggerUrl) return page.webSocketDebuggerUrl;
        } catch {}
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
    throw new Error('Chrome DevTools page was unavailable');
}

async function inspect(socket, route, width, height) {
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        }
    };
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
    await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width <= 640 });
    await command('Page.navigate', { url: `${baseUrl}index.php?page=${encodeURIComponent(route)}` });
    await new Promise((resolve) => setTimeout(resolve, 400));
    const expression = `(() => {
        const activeNav = document.querySelector('.primary-nav a[aria-current="page"]');
        const focusTarget = [...document.querySelectorAll('a, button, input, select')].find((element) => {
            const style = getComputedStyle(element);
            return !element.hidden && style.display !== 'none' && style.visibility !== 'hidden';
        });
        if (focusTarget) focusTarget.focus();
        const focusStyle = focusTarget ? getComputedStyle(focusTarget) : null;
        const visibleControls = [...document.querySelectorAll('a, button, input, select')].filter((element) => {
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return !element.hidden && style.display !== 'none' && box.width > 0 && box.height > 0;
        });
        const clipped = [...document.querySelectorAll('h1, h2, button, .status-pill')].filter((element) =>
            element.scrollWidth > element.clientWidth + 1 && getComputedStyle(element).overflowX !== 'auto'
        ).length;
        const tableWrap = document.querySelector('.table-wrap');
        const main = document.querySelector('main');
        const shortestControl = visibleControls.sort((left, right) => left.getBoundingClientRect().height - right.getBoundingClientRect().height)[0];
        const overflowElement = [...document.querySelectorAll('body *')].find((element) => {
            const box = element.getBoundingClientRect();
            return box.right > document.documentElement.clientWidth + 1 || box.left < -1;
        });
        window.scrollTo(10000, 0);
        const pageScrollX = window.scrollX;
        window.scrollTo(0, 0);
        return {
            page: document.body.dataset.page,
            titleCount: document.querySelectorAll('h1').length,
            editorialSections: document.querySelectorAll('.editorial-section').length,
            legacyContainers: document.querySelectorAll('main .panel, main .content-section, .dashboard-hero').length,
            documentOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            pageScrollX,
            documentWidths: document.documentElement.scrollWidth + '/' + document.documentElement.clientWidth,
            mainOverflow: main.scrollWidth > main.clientWidth,
            overflowElement: overflowElement ? overflowElement.tagName.toLowerCase() + '.' + overflowElement.className : '',
            clipped,
            activeNav: activeNav ? activeNav.getAttribute('href') : '',
            focusWidth: focusStyle ? parseFloat(focusStyle.outlineWidth) : 0,
            minControlHeight: visibleControls.length ? Math.min(...visibleControls.map((element) => element.getBoundingClientRect().height)) : 0,
            shortestControl: shortestControl ? shortestControl.tagName.toLowerCase() + '.' + shortestControl.className + ':' + shortestControl.textContent.trim() : '',
            mobileToggleVisible: getComputedStyle(document.querySelector('[data-nav-toggle]')).display !== 'none',
            tableContained: !tableWrap || tableWrap.getBoundingClientRect().width <= main.getBoundingClientRect().width + 1
            ,tableMetrics: tableWrap ? [tableWrap.getBoundingClientRect().left, tableWrap.getBoundingClientRect().right, tableWrap.getBoundingClientRect().width, tableWrap.scrollWidth, getComputedStyle(tableWrap).overflowX].join('/') : ''
            ,mainMetrics: [main.getBoundingClientRect().left, main.getBoundingClientRect().right, main.getBoundingClientRect().width, main.scrollWidth].join('/')
        };
    })()`;
    const evaluated = await command('Runtime.evaluate', { expression, returnByValue: true });
    if (evaluated.exceptionDetails) throw new Error(evaluated.exceptionDetails.text);
    return evaluated.result.value;
}

(async () => {
    assert(fs.existsSync(chrome), 'Chrome is required for responsive layout verification');
    const port = await freePort();
    const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'seamless-editorial-responsive-'));
    const chromeProcess = spawn(chrome, [
        '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run',
        `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'
    ], { stdio: 'ignore' });
    try {
        const socket = new WebSocket(await waitForPage(port));
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        for (const [route, minimumSections] of Object.entries(pages)) {
            for (const viewport of [[1440, 1000], [820, 900], [390, 844]]) {
                const result = await inspect(socket, route, ...viewport);
                const context = `${route} at ${viewport[0]}px`;
                assert(result.page === route, `${context} rendered the wrong route`);
                assert(result.titleCount === 1, `${context} must contain one page title`);
                assert(result.editorialSections >= minimumSections, `${context} is missing editorial sections`);
                assert(result.legacyContainers === 0, `${context} still renders card-heavy or hero containers`);
                assert(result.pageScrollX === 0, `${context} permits horizontal page scrolling by ${result.pageScrollX}px: ${result.overflowElement}; table ${result.tableMetrics}; main ${result.mainMetrics}`);
                assert(result.clipped === 0, `${context} clips heading, button, or status text`);
                assert(result.activeNav.includes(`page=${route}`), `${context} has the wrong active navigation item`);
                assert(result.focusWidth >= 2, `${context} does not expose a visible focus outline`);
                assert(result.minControlHeight >= 44, `${context} has an interactive target below 44px: ${result.shortestControl} (${result.minControlHeight}px)`);
                assert(result.tableContained, `${context} lets the history table escape its content region`);
                assert(result.mobileToggleVisible === (viewport[0] <= 960), `${context} has the wrong navigation mode`);
            }
        }
        socket.close();
        console.log('PASS seamless editorial layout is responsive and accessible across six Apache routes');
    } finally {
        if (!chromeProcess.killed) chromeProcess.kill();
        await new Promise((resolve) => chromeProcess.exitCode !== null ? resolve() : chromeProcess.once('exit', resolve));
        for (let attempt = 0; attempt < 5; attempt++) {
            try { fs.rmSync(profile, { recursive: true, force: true }); break; }
            catch (error) { if (attempt === 4) throw error; await new Promise((resolve) => setTimeout(resolve, 100)); }
        }
    }
})().catch((error) => { console.error(`FAIL ${error.message}`); process.exitCode = 1; });
