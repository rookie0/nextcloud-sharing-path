$(document).ready(function () {
  OCA.Files.fileActions.registerAction({
    name: 'copy-sharing-path',
    displayName: ['zh-CN', 'zh-HK', 'zh-TW', 'ja', 'ko'].includes(OC.getLanguage()) ? (t('files', 'Copy') + t('files_sharing', 'Sharing') + t('files', 'Path')) : (t('files', 'Copy') + ' ' + t('files_sharing', 'Sharing') + ' ' + t('files', 'Path')),
    mime: 'file',
    permissions: OC.PERMISSION_READ,
    iconClass: 'icon-public',
    actionHandler: function (filename, context) {
      if (context.$file.data('shareTypes') !== OC.Share.SHARE_TYPE_LINK) {
        OC.dialogs.info(t('files_sharing', 'No shared links'), t('gallery', 'Warning'));
        return;
      }

      var path = OC.getProtocol() + '://' + OC.getHost() + OC.generateUrl('/apps/sharingpath/' + OC.getCurrentUser().uid + (context.dir === '/' ? '' : context.dir) + '/' + filename);

      var dummyPath = document.createElement('textarea');
      dummyPath.value = path;
      dummyPath.setAttribute('readonly', '');
      dummyPath.style.position = 'absolute';
      dummyPath.style.left = '-9999px';
      document.body.appendChild(dummyPath);
      const selected =
          document.getSelection().rangeCount > 0        // Check if there is any content selected previously
              ? document.getSelection().getRangeAt(0)   // Store selection if found
              : false;

      dummyPath.select();
      document.execCommand("copy");
      document.body.removeChild(dummyPath);
      if (selected) {                                 // If a selection existed before copying
        document.getSelection().removeAllRanges();    // Unselect everything on the HTML document
        document.getSelection().addRange(selected);   // Restore the original selection
      }
    }
  });

});