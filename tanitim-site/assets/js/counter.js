(function () {
  "use strict";

  var section = document.querySelector(".impact-stats");
  if (!section) return;

  var numbers = Array.prototype.slice.call(section.querySelectorAll(".impact-stat-number"));
  if (!numbers.length) return;

  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function formatValue(value, format) {
    var rounded = Math.round(value);
    if (format === "tr") {
      return rounded.toLocaleString("tr-TR");
    }
    return String(rounded);
  }

  function animateCounter(el) {
    var target = parseInt(el.getAttribute("data-counter-target"), 10);
    if (isNaN(target)) return;
    var format = el.getAttribute("data-counter-format");
    var duration = 1200;
    var startTime = null;

    function step(timestamp) {
      if (startTime === null) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var current = target * eased;
      el.textContent = formatValue(current, format);
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.textContent = formatValue(target, format);
      }
    }

    window.requestAnimationFrame(step);
  }

  if (reduceMotion || typeof window.IntersectionObserver !== "function") {
    numbers.forEach(function (el) {
      var target = parseInt(el.getAttribute("data-counter-target"), 10);
      var format = el.getAttribute("data-counter-format");
      if (!isNaN(target)) el.textContent = formatValue(target, format);
    });
    return;
  }

  var hasRun = false;
  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && !hasRun) {
          hasRun = true;
          numbers.forEach(animateCounter);
          observer.disconnect();
        }
      });
    },
    { threshold: 0.4 }
  );

  observer.observe(section);
})();
