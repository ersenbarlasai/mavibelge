/* Referanslarımız logo slider — ortak bileşen, MB_REFERENCES verisinden beslenir. */
(function () {
  "use strict";

  var CLONE_COUNT = 6;
  var AUTOPLAY_MS = 4000;

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function buildCard(ref, hidden) {
    var li = document.createElement("li");
    li.className = "ref-card";
    if (hidden) li.setAttribute("aria-hidden", "true");
    var img = document.createElement("img");
    img.src = ref.file;
    img.alt = hidden ? "" : ref.alt;
    img.loading = "lazy";
    img.width = 220;
    img.height = 64;
    li.appendChild(img);
    return li;
  }

  function initSlider(root) {
    var data = window.MB_REFERENCES || [];
    if (!data.length) return;

    var viewport = root.querySelector("[data-ref-viewport]");
    var track = root.querySelector("[data-ref-track]");
    var prevBtn = root.querySelector("[data-ref-prev]");
    var nextBtn = root.querySelector("[data-ref-next]");
    if (!viewport || !track) return;

    track.innerHTML = "";
    var tailClones = data.slice(data.length - CLONE_COUNT);
    var headClones = data.slice(0, CLONE_COUNT);

    tailClones.forEach(function (ref) { track.appendChild(buildCard(ref, true)); });
    data.forEach(function (ref) { track.appendChild(buildCard(ref, false)); });
    headClones.forEach(function (ref) { track.appendChild(buildCard(ref, true)); });

    var total = data.length;
    var pos = CLONE_COUNT;
    var reduced = prefersReducedMotion();

    function cardStep() {
      var card = track.children[0];
      if (!card) return 0;
      var rect = card.getBoundingClientRect();
      var styles = window.getComputedStyle(track);
      var gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
      return rect.width + gap;
    }

    function applyTransform(withTransition) {
      track.style.transition = withTransition && !reduced ? "" : "none";
      track.style.transform = "translateX(-" + (pos * cardStep()) + "px)";
      if (!withTransition) {
        // force reflow so the removed transition takes effect before re-enabling
        void track.offsetHeight;
        track.style.transition = "";
      }
    }

    function normalize() {
      if (pos >= CLONE_COUNT + total) {
        pos -= total;
        applyTransform(false);
      } else if (pos < CLONE_COUNT) {
        pos += total;
        applyTransform(false);
      }
    }

    track.addEventListener("transitionend", normalize);

    function goTo(next) {
      pos = next;
      applyTransform(true);
      if (reduced) normalize();
    }

    function next() { goTo(pos + 1); }
    function prev() { goTo(pos - 1); }

    if (nextBtn) nextBtn.addEventListener("click", function () { next(); restartAutoplay(); });
    if (prevBtn) prevBtn.addEventListener("click", function () { prev(); restartAutoplay(); });

    root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") { next(); restartAutoplay(); }
      else if (e.key === "ArrowLeft") { prev(); restartAutoplay(); }
    });

    // Dokunmatik kaydırma
    var touchStartX = null;
    viewport.addEventListener("touchstart", function (e) {
      touchStartX = e.touches[0].clientX;
      stopAutoplay();
    }, { passive: true });
    viewport.addEventListener("touchend", function (e) {
      if (touchStartX === null) return;
      var dx = e.changedTouches[0].clientX - touchStartX;
      if (dx < -40) next();
      else if (dx > 40) prev();
      touchStartX = null;
      restartAutoplay();
    }, { passive: true });

    // Otomatik ilerleme kontrolü
    var timer = null;
    var isVisible = true;

    function startAutoplay() {
      if (reduced || timer || !isVisible) return;
      timer = window.setInterval(next, AUTOPLAY_MS);
    }
    function stopAutoplay() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }
    function restartAutoplay() {
      stopAutoplay();
      window.setTimeout(startAutoplay, 600);
    }

    root.addEventListener("mouseenter", stopAutoplay);
    root.addEventListener("mouseleave", startAutoplay);
    root.addEventListener("focusin", stopAutoplay);
    root.addEventListener("focusout", function (e) {
      if (!root.contains(e.relatedTarget)) startAutoplay();
    });

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) stopAutoplay(); else startAutoplay();
    });

    if (window.IntersectionObserver) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          isVisible = entry.isIntersecting;
          if (isVisible) startAutoplay(); else stopAutoplay();
        });
      }, { threshold: 0.2 });
      io.observe(root);
    }

    window.addEventListener("resize", function () { applyTransform(false); });

    applyTransform(false);
    startAutoplay();
  }

  function initGrid(root) {
    var data = window.MB_REFERENCES || [];
    root.innerHTML = "";
    data.forEach(function (ref) {
      var li = document.createElement("li");
      li.className = "ref-card";
      var img = document.createElement("img");
      img.src = ref.file;
      img.alt = ref.alt;
      img.loading = "lazy";
      img.width = 220;
      img.height = 64;
      li.appendChild(img);
      root.appendChild(li);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-ref-slider]").forEach(initSlider);
    document.querySelectorAll("[data-ref-grid]").forEach(initGrid);
  });
})();
