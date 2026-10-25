(function() {
  'use strict';
  function init() {
    const panel = document.getElementById('sharingPath');
    if (!panel || panel.dataset.initialized) return;
    panel.dataset.initialized = 'yes';
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    panel.appendChild(status);
    let pending = Promise.resolve();
    let pendingCount = 0;
    let saveError = '';
    function save(endpoint, key, value) {
      if (pendingCount === 0) saveError = '';
      pendingCount++;
      const body = JSON.stringify({ type: panel.querySelector('#type').value, [key]: value });
      status.textContent = 'Saving…';
      // Preserve user intent when rapid changes would otherwise race on the server.
      pending = pending.then(async function() {
        try {
          const response = await fetch(OC.generateUrl('/apps/sharingpath/settings/' + endpoint), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken || document.head.dataset.requesttoken || '' },
            body,
          });
          if (!response.ok) throw new Error('Saving failed (' + response.status + ')');
        } catch (error) {
          saveError = error.message;
        } finally {
          pendingCount--;
          if (pendingCount === 0) status.textContent = saveError || 'Saved';
        }
      });
    }
    panel.querySelector('#enableSharingPath').addEventListener('change', function() {
      save('enable', 'enabled', this.checked ? 'yes' : 'no');
    });
    [['copyPrefix', 'copyprefix', 'prefix'], ['sharingFolder', 'sharingfolder', 'folder']].forEach(function([id, endpoint, key]) {
      const input = panel.querySelector('#' + id);
      input.addEventListener('blur', () => save(endpoint, key, input.value));
      input.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') { event.preventDefault(); input.blur(); }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
