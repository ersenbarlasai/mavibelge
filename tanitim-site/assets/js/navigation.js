// Mavi Belge — masaüstü açılır menü + mobil hamburger menü
(function () {
  function closeAllSubmenus(except) {
    document.querySelectorAll(".main-nav li.is-open").forEach(function (li) {
      if (li !== except) {
        li.classList.remove("is-open");
        var t = li.querySelector(".nav-toggle");
        if (t) t.setAttribute("aria-expanded", "false");
      }
    });
  }

  document.querySelectorAll(".main-nav .nav-toggle").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var li = btn.closest("li");
      var isOpen = li.classList.contains("is-open");
      closeAllSubmenus(isOpen ? null : li);
      li.classList.toggle("is-open", !isOpen);
      btn.setAttribute("aria-expanded", String(!isOpen));
    });
  });

  document.addEventListener("click", function (e) {
    if (!e.target.closest(".main-nav")) closeAllSubmenus(null);
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeAllSubmenus(null);
  });

  var toggle = document.querySelector(".menu-toggle");
  var nav = document.getElementById("main-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });
  }

  // Mark current page in nav
  var path = location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".main-nav a, .side-nav a").forEach(function (a) {
    var href = a.getAttribute("href");
    if (href && href.split("?")[0] === path) {
      a.classList.add("is-active");
      a.setAttribute("aria-current", "page");
    }
  });
})();
