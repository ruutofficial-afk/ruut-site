/* ============================================================
   RUUT — Scroll FX
   Lightweight, dependency-free scroll-reveal + parallax.
   Purely additive & presentational. Honors prefers-reduced-motion.
   ============================================================ */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function init() {
    var revealItems = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));

    if (reduced || !('IntersectionObserver' in window)) {
      // Show everything immediately, no motion.
      revealItems.forEach(function (el) { el.classList.add('is-revealed'); });
      return;
    }

    // Staggered reveal within a shared container
    revealItems.forEach(function (el) {
      var delay = el.getAttribute('data-reveal-delay');
      if (delay) el.style.transitionDelay = delay + 'ms';
    });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });

    revealItems.forEach(function (el) { io.observe(el); });

    /* ---- Subtle parallax on tagged imagery ---- */
    var parallaxItems = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    if (parallaxItems.length) {
      var ticking = false;
      var update = function () {
        var vh = window.innerHeight;
        parallaxItems.forEach(function (el) {
          var rect = el.getBoundingClientRect();
          if (rect.bottom < 0 || rect.top > vh) return;
          var speed = parseFloat(el.getAttribute('data-parallax')) || 0.12;
          var offset = (rect.top + rect.height / 2 - vh / 2) * -speed;
          el.style.transform = 'translate3d(0,' + offset.toFixed(2) + 'px,0)';
        });
        ticking = false;
      };
      window.addEventListener('scroll', function () {
        if (!ticking) {
          window.requestAnimationFrame(update);
          ticking = true;
        }
      }, { passive: true });
      update();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
