$(document).ready(function () {

  // todo copy path to clipboard
  OCA.Files.fileActions.registerAction({
    name: 'copy-sharing-path',
    displayName: ['zh-CN', 'zh-HK', 'zh-TW', 'ja', 'ko'].includes(OC.getLanguage()) ? (t('files', 'Open') + t('files_sharing', 'Sharing') + t('files', 'Path')) : (t('files', 'Open') + ' ' + t('files_sharing', 'Sharing') + ' ' + t('files', 'Path')),
    mime: 'file',
    permissions: OC.PERMISSION_READ,
    iconClass: 'icon-public',
    actionHandler: function (filename, context) {
      if (context.$file.data('shareTypes') !== OC.Share.SHARE_TYPE_LINK) {
        OC.dialogs.info(t('files_sharing', 'No shared links'), t('gallery', 'Warning'));
        return;
      }

      var path = OC.getProtocol() + '://' + OC.getHost() + OC.generateUrl('/apps/sharingpath/' + OC.getCurrentUser().uid + context.dir + '/' + filename);
      window.open(path);
    }
  });

});
