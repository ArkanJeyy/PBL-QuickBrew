/* Badge jumlah item pada ikon keranjang. Bergantung pada cart-store.js. */
(function () {
  'use strict';

  var cart = window.QuickBrewCart;
  var badges = document.querySelectorAll('[data-cart-badge]');
  if (!cart || !badges.length) return;

  var update = function () {
    var count = cart.count();
    Array.prototype.forEach.call(badges, function (badge) {
      badge.hidden = count < 1;
      badge.textContent = count > 99 ? '99+' : String(count);
      var link = badge.closest('[data-cart-link]');
      if (link) {
        link.setAttribute('aria-label', count > 0 ? 'Keranjang, ' + count + ' item' : 'Keranjang');
      }
    });
  };

  document.addEventListener('cart:change', update);
  window.addEventListener('storage', function (event) {
    if (event.key === cart.key) update();
  });
  window.addEventListener('pageshow', update); // kembali dari halaman Keranjang (bfcache)

  update();
})();
