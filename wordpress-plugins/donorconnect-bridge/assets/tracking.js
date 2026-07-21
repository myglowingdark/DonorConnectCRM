(function () {
  if (!window.dcBridgeTracking) {
    return;
  }

  var cfg = window.dcBridgeTracking;
  var cookieName = cfg.cookieName || 'dca';
  var queryParam = cfg.queryParam || 'dcr';
  var days = parseInt(cfg.cookieDays || 3, 10);

  function readQuery(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  function setCookie(name, value, maxDays) {
    var maxAge = Math.max(1, maxDays) * 24 * 60 * 60;
    document.cookie =
      name +
      '=' +
      encodeURIComponent(value) +
      '; path=/; max-age=' +
      maxAge +
      '; SameSite=Lax';
  }

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  function postEvent(payload) {
    if (!cfg.eventsUrl || !payload.dcr) {
      return;
    }
    try {
      fetch(cfg.eventsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
        mode: 'cors',
      }).catch(function () {});
    } catch (e) {}
  }

  var dcr = readQuery(queryParam) || getCookie(cookieName);
  if (readQuery(queryParam)) {
    setCookie(cookieName, readQuery(queryParam), days);
    dcr = readQuery(queryParam);
  }

  if (dcr) {
    postEvent({
      dcr: dcr,
      event_type: 'page_view',
      page_url: window.location.href,
    });
  }

  function injectUtm(formData) {
    if (!dcr) {
      return formData;
    }
    if (typeof formData.append === 'function') {
      formData.append('utm_source', cfg.utmSource || 'donorconnect');
      formData.append('utm_content', dcr);
      formData.append('dcr', dcr);
      return formData;
    }
    formData.utm_source = cfg.utmSource || 'donorconnect';
    formData.utm_content = dcr;
    formData.dcr = dcr;
    return formData;
  }

  // Patch jQuery AJAX used by NGOBuddy create order when available.
  function patchJquery() {
    if (!window.jQuery || window.jQuery._dcBridgeTrackingPatched) {
      return;
    }
    window.jQuery._dcBridgeTrackingPatched = true;
    window.jQuery(document).ajaxSend(function (_event, _jqXHR, settings) {
      if (!dcr || !settings || !settings.data) {
        return;
      }
      var url = String(settings.url || '');
      if (url.indexOf('admin-ajax.php') === -1) {
        return;
      }
      var data = settings.data;
      if (typeof data === 'string') {
        if (data.indexOf('action=gdnb_create_order') === -1) {
          return;
        }
        if (data.indexOf('utm_content=') === -1) {
          settings.data =
            data +
            '&utm_source=' +
            encodeURIComponent(cfg.utmSource || 'donorconnect') +
            '&utm_content=' +
            encodeURIComponent(dcr) +
            '&dcr=' +
            encodeURIComponent(dcr);
        }
        postEvent({
          dcr: dcr,
          event_type: 'checkout_started',
          page_url: window.location.href,
        });
      }
    });
  }

  patchJquery();
  document.addEventListener('DOMContentLoaded', patchJquery);

  // Expose helper for custom themes.
  window.dcBridgeInjectTracking = injectUtm;
})();
