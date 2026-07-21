(function ($) {
  $('#dc-bridge-push-now').on('click', function () {
    var $btn = $(this);
    var $out = $('#dc-bridge-push-result');
    $btn.prop('disabled', true);
    $out.prop('hidden', false).text('Pushing…');
    $.post(dcBridge.ajaxUrl, {
      action: 'dc_bridge_push_now',
      nonce: dcBridge.nonce
    })
      .done(function (res) {
        $out.text(JSON.stringify(res, null, 2));
      })
      .fail(function (xhr) {
        $out.text(xhr.responseText || 'Request failed');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });
})(jQuery);
