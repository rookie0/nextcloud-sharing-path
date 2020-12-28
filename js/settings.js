window.addEventListener('DOMContentLoaded', function(event) {
  $('#enableSharingPath').bind('change', function() {
    $.ajax(OC.generateUrl('/apps/sharingpath/settings/enable'), {
      type: 'PUT',
      data: {
        type: $('#type').val(),
        enabled: $(this).is(':checked') ? 'yes' : 'no',
      },
    });
  });

  $('#copyPrefix').on('blur keydown', function(e) {
    if (e.type === 'blur' || (e.type === 'keydown' && e.keyCode === 13)) {
      $.ajax(OC.generateUrl('/apps/sharingpath/settings/copyprefix'), {
        type: 'PUT',
        data: {
          type: $('#type').val(),
          prefix: $(this).val(),
        },
      });
    }
  });

  $('#sharingFolder').on('blur keydown', function(e) {
    if (e.type === 'blur' || (e.type === 'keydown' && e.keyCode === 13)) {
      $.ajax(OC.generateUrl('/apps/sharingpath/settings/sharingfolder'), {
        type: 'PUT',
        data: {
          type: $('#type').val(),
          folder: $(this).val(),
        },
      });
    }
  });
});