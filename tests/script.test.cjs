const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/script.js'), 'utf8');
async function setup(settings = { enabled: 'yes' }, uid = 'alice') {
  const copied = [];
  const window = { location: { origin: 'https://cloud.example' }, isSecureContext: true };
  const OC = { generateUrl: p => '/nextcloud/index.php' + p, getCurrentUser: () => ({ uid }), getLanguage: () => 'en' };
  window.OC = OC;
  const context = { window, OC, navigator: { clipboard: { writeText: async text => copied.push(text) } },
    document: { readyState: 'complete', head: { dataset: {} }, documentElement: { lang: 'en' } },
    fetch: async () => ({ ok: true, json: async () => settings }), console, EventTarget, CustomEvent };
  vm.runInNewContext(source, context);
  await new Promise(resolve => setImmediate(resolve));
  return { action: window._nc_files_scope.v4_0.fileActions.get('copy-sharing-path'), copied, window };
}
test('subdirectory installation and reserved filename characters', async () => {
  const { action, copied } = await setup();
  assert.equal(action.enabled({ nodes: [{ type: 'file' }] }), true);
  assert.equal(await action.exec({ nodes: [{ path: '/public/a #?% ü.txt' }] }), true);
  assert.equal(copied[0], 'https://cloud.example/nextcloud/index.php/apps/sharingpath/alice/public/a%20%23%3F%25%20%C3%BC.txt');
});
test('v3 action arguments and user id are encoded separately', async () => {
  const { action, copied } = await setup({ default_enabled: 'yes' }, 'a#b');
  assert.equal(action.enabled([{ type: 'file' }]), true);
  await action.exec({ path: '/file.txt' });
  assert.equal(copied[0], 'https://cloud.example/nextcloud/index.php/apps/sharingpath/a%23b/file.txt');
});
test('custom prefix is preserved without double encoding', async () => {
  const { action, copied } = await setup({ enabled: 'yes', copy_prefix: 'https://cdn.example/encoded%20user/' });
  await action.exec({ path: '/a%.txt' });
  assert.equal(copied[0], 'https://cdn.example/encoded%20user/a%25.txt');
});
test('disabled setting overrides enabled default and folders remain hidden', async () => {
  const { action } = await setup({ enabled: 'no', default_enabled: 'yes' });
  assert.equal(action.enabled([{ type: 'file' }]), false);
  assert.equal(action.enabled([{ type: 'folder' }]), false);
});
