const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('rapid enable/disable changes save in order; failed writes are visible', async () => {
  const inputs = Object.fromEntries(['type', 'enableSharingPath', 'copyPrefix', 'sharingFolder'].map(id => [id, {
    value: id === 'type' ? 'personal' : '', handlers: {},
    addEventListener(event, fn) { this.handlers[event] = fn; },
  }]));
  const status = { setAttribute() {} };
  const panel = { dataset: {}, appendChild() {}, querySelector: selector => inputs[selector.slice(1)] };
  const requests = [];
  const document = { readyState: 'complete', getElementById: () => panel, createElement: () => status };
  const fetch = (url, options) => new Promise(resolve => requests.push({ url, body: JSON.parse(options.body), resolve }));
  vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../js/settings.js'), 'utf8'), {
    document, fetch, OC: { generateUrl: p => p, requestToken: 'fixture' },
  });
  const toggle = inputs.enableSharingPath;
  toggle.checked = true;
  toggle.handlers.change.call(toggle);
  toggle.checked = false;
  toggle.handlers.change.call(toggle);
  const tick = () => new Promise(resolve => setImmediate(resolve));
  await tick();
  assert.equal(requests.length, 1, 'second write must wait for the first response');
  assert.equal(requests[0].body.enabled, 'yes');
  requests[0].resolve({ ok: true });
  await tick();
  assert.equal(status.textContent, 'Saving…');
  assert.equal(requests.length, 2);
  assert.equal(requests[1].body.enabled, 'no');
  requests[1].resolve({ ok: false, status: 403 });
  await tick();
  assert.equal(status.textContent, 'Saving failed (403)');
  toggle.handlers.change.call(toggle);
  inputs.copyPrefix.handlers.blur.call(inputs.copyPrefix);
  await tick();
  requests[2].resolve({ ok: false, status: 500 });
  await tick();
  requests[3].resolve({ ok: true });
  await tick();
  assert.equal(status.textContent, 'Saving failed (500)', 'later success must not conceal an earlier failed field');
});
