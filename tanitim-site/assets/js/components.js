// Mavi Belge — SSS akordeonu ve sayfa başına dön düğmesi
(function () {
  document.querySelectorAll(".accordion-trigger").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var expanded = btn.getAttribute("aria-expanded") === "true";
      var panel = document.getElementById(btn.getAttribute("aria-controls"));
      btn.setAttribute("aria-expanded", String(!expanded));
      if (panel) panel.classList.toggle("is-open", !expanded);
    });
  });

  var backToTop = document.querySelector(".back-to-top");
  if (backToTop) {
    window.addEventListener("scroll", function () {
      backToTop.classList.toggle("is-visible", window.scrollY > 480);
    });
    backToTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Yıl otomatik güncelleme
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });
})();
