'use strict';

const assert = require('assert');
const ui = require('../public/assets/restore-integration.js');

assert.strictEqual(ui.canExecuteLive({ live_execution_enabled: false }), false);
assert.strictEqual(ui.canExecuteLive({ live_execution_enabled: true }), false);
assert.strictEqual(ui.safeStatus({ status: 'running' }), 'running');
assert.strictEqual(ui.safeStatus({ status: '<script>' }), 'blocked');
console.log('PASS restore integration UI remains live-disabled and sanitizes states');
