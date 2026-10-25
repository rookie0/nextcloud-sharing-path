(function() {
  'use strict';
  function init() {
    const panel = document.getElementById('sharingPath');
    if (!panel || panel.dataset.initialized) return;
    panel.dataset.initialized = 'yes';
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    panel.appendChild(status);
    async function save(endpoint, key, value) {
      status.textContent = 'Saving…';
      try {
        const response = await fetch(OC.generateUrl('/apps/sharingpath/settings/' + endpoint), {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken || document.head.dataset.requesttoken || '' },
          body: JSON.stringify({ type: panel.querySelector('#type').value, [key]: value }),
        });
        if (!response.ok) throw new Error('Saving failed (' + response.status + ')');
        status.textContent = 'Saved';
      } catch (error) {
        status.textContent = error.message;
      }
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
