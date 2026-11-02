/* Hibira Realtime for Craft: control panel. */
(function () {
  'use strict';

  var t = function (msg, params) {
    return window.Craft && Craft.t ? Craft.t('hibira', msg, params) : msg;
  };

  function connect(cfg) {
    var opts = {
      wsHost: cfg.wsHost,
      wsPort: cfg.wsPort,
      wssPort: cfg.wssPort,
      forceTLS: cfg.forceTLS,
      cluster: cfg.cluster,
      enabledTransports: cfg.enabledTransports,
      channelAuthorization: { endpoint: cfg.authEndpoint, transport: 'ajax' },
    };
    window.HibiraCraftPusher = window.HibiraCraftPusher || new Pusher(cfg.key, opts);
    return window.HibiraCraftPusher;
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function presence(box) {
    var cfg = JSON.parse(box.getAttribute('data-hibira'));
    var pusher = connect(cfg);
    var channel = pusher.subscribe(cfg.channel);
    var others = el('div', 'hibira-others');
    var saved = el('div', 'hibira-saved');
    box.appendChild(others);
    box.appendChild(saved);

    function render() {
      others.textContent = '';
      var list = [];
      channel.members.each(function (m) {
        if (String(m.id) !== String(cfg.userId) && list.every(function (x) { return x.id !== m.id; })) list.push(m);
      });
      box.classList.toggle('hibira-active', list.length > 0 || saved.childNodes.length > 0);
      if (!list.length) return;
      others.appendChild(el('span', 'hibira-label', t('Also editing:')));
      list.forEach(function (m) {
        var info = m.info || {};
        var chip = el('span', 'hibira-chip');
        if (info.photo) {
          var img = el('img');
          img.src = info.photo;
          img.alt = '';
          chip.appendChild(img);
        }
        chip.appendChild(el('span', null, info.name || '#' + m.id));
        others.appendChild(chip);
      });
    }

    channel.bind('pusher:subscription_succeeded', render);
    channel.bind('pusher:member_added', render);
    channel.bind('pusher:member_removed', render);
    channel.bind('entry-saved', function (data) {
      if (data && String(data.userId) === String(cfg.userId)) return;
      var name = (data && data.name) || '';
      saved.textContent = '';
      saved.appendChild(el('span', null, t('{name} saved this entry. Reload to see their changes.', { name: name })));
      var btn = el('button', 'btn small', t('Reload'));
      btn.type = 'button';
      btn.addEventListener('click', function () {
        window.location.reload();
      });
      saved.appendChild(btn);
      render();
      if (window.Craft && Craft.cp) Craft.cp.displayNotice(t('{name} saved this entry.', { name: name }));
    });
  }

  function orders(cfg) {
    var pusher = connect(cfg);
    pusher.subscribe(cfg.channel).bind('new-order', function (data) {
      if (window.Craft && Craft.cp) Craft.cp.displayNotice(t('New order {reference}', { reference: (data && data.reference) || '' }) + (data && data.total ? ' · ' + data.total : ''));
    });
  }

  function start() {
    var box = document.getElementById('hibira-presence');
    if (box && window.Pusher) presence(box);
    if (window.HibiraCraftOrders && window.Pusher) orders(window.HibiraCraftOrders);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
