window.addEventListener('DOMContentLoaded', function(event) {
  let settings = {
    default_enabled: '',
    enabled: '',
    default_copy_prefix: '',
    copy_prefix: '',
  };

  $.ajax(OC.generateUrl('/apps/sharingpath/settings'), {
    type: 'GET',
    dataType: 'json',
    success: function(data) {
      settings = data || settings;

      if (settings.enabled === 'yes' || (!settings.enabled && settings.default_enabled === 'yes')) {
        OCA.Files.fileActions.registerAction({
          name: 'copy-sharing-path',
          displayName: [ 'zh-CN', 'zh-HK', 'zh-TW', 'ja', 'ko' ].includes(OC.getLanguage()) ?
            (t('files', 'Copy') + t('files_sharing', 'Sharing') + t('files', 'Path')) :
            (t('files', 'Copy') + ' ' + t('files_sharing', 'Sharing') + ' ' + t('files', 'Path')),
          mime: 'file',
          permissions: OC.PERMISSION_READ,
          iconClass: 'icon-public',
          actionHandler: function(filename, context) {
            let prefix = OC.getProtocol() + '://' + OC.getHost() + '/apps/sharingpath/';
            // admin setting
            prefix = settings.default_copy_prefix || prefix;
            prefix = prefix.endsWith('/') ? prefix : (prefix + '/');
            prefix += OC.getCurrentUser().uid;
            // user setting
            prefix = settings.copy_prefix || prefix;
            prefix = prefix.endsWith('/') ? prefix.substring(0, prefix.length - 1) : prefix;

            let path = encodeURI(prefix + (context.dir === '/' ? '' : context.dir) + '/' + filename);
            let dummyPath = document.createElement('textarea');
            dummyPath.value = path;
            dummyPath.setAttribute('readonly', '');
            dummyPath.style.position = 'absolute';
            dummyPath.style.left = '-9999px';
            document.body.appendChild(dummyPath);
            const selected =
              document.getSelection().rangeCount > 0        // Check if there is any content selected previously
                ? document.getSelection().getRangeAt(0)     // Store selection if found
                : false;

            dummyPath.select();
            document.execCommand("copy");
            document.body.removeChild(dummyPath);
            if (selected) {                                 // If a selection existed before copying
              document.getSelection().removeAllRanges();    // Unselect everything on the HTML document
              document.getSelection().addRange(selected);   // Restore the original selection
            }
          },
        });
      }
    },
  });
});
