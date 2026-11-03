/* Hibira Realtime for Craft: front end. window.HibiraCraft.pusher is a pusher-js client. */
(function () {
  'use strict';
  var cfg = window.HibiraCraftConfig;
  if (!cfg || !window.Pusher || window.HibiraCraft) return;

  var pusher = new Pusher(cfg.key, {
    wsHost: cfg.wsHost,
    wsPort: cfg.wsPort,
    wssPort: cfg.wssPort,
    forceTLS: cfg.forceTLS,
    cluster: cfg.cluster,
    enabledTransports: cfg.enabledTransports,
    channelAuthorization: { endpoint: cfg.authEndpoint, transport: 'ajax' },
  });

  function notice() {
    if (document.getElementById('hibira-updated')) return;
    var box = document.createElement('div');
    box.id = 'hibira-updated';
    box.setAttribute('role', 'status');
    box.style.cssText =
      'position:fixed;left:50%;bottom:20px;transform:translateX(-50%);z-index:2147483000;display:flex;gap:12px;align-items:center;' +
      'padding:10px 14px;border-radius:8px;background:#111827;color:#fff;font:14px/1.4 system-ui,sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.25)';
    var text = document.createElement('span');
    text.textContent = cfg.strings.updated;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = cfg.strings.reload;
    btn.style.cssText = 'border:0;border-radius:6px;padding:6px 10px;background:#fff;color:#111827;font:inherit;cursor:pointer';
    btn.addEventListener('click', function () {
      window.location.reload();
    });
    box.appendChild(text);
    box.appendChild(btn);
    document.body.appendChild(box);
  }

  window.HibiraCraft = {
    pusher: pusher,
    /** Calls cb(data) when entry id is saved while live. */
    onEntryUpdated: function (id, cb) {
      pusher.subscribe('craft-entry-' + id).bind('entry-updated', cb);
    },
    /** Calls cb(data) when any live entry of the section is saved. */
    onSectionSaved: function (handle, cb) {
      pusher.subscribe('craft-section-' + handle).bind('entry-saved', cb);
    },
    /** Calls cb(data) when a Commerce order's status changes (data.status, data.handle). */
    onOrderStatus: function (number, cb) {
      pusher.subscribe('private-craft-order-' + number).bind('status', cb);
    },
    notice: notice,
  };

  var seen = {};
  document.querySelectorAll('[data-hibira-entry]').forEach(function (node) {
    var id = node.getAttribute('data-hibira-entry');
    if (!id || seen[id]) return;
    seen[id] = true;
    window.HibiraCraft.onEntryUpdated(id, notice);
  });
})();
