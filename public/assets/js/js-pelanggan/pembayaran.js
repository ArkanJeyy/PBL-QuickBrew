/* Halaman Pembayaran: kosongkan keranjang satu kali tepat setelah pesanan dibuat. Vanilla JS. */
(function () {
  'use strict';

  var cart = window.QuickBrewCart;
  if (cart && document.querySelector('[data-clear-cart]')) {
    cart.clear();
  }
})();
