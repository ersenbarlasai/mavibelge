// Mavi Belge — "Mesleğini Bul" canlı arama önerileri
(function () {
  function norm(s) {
    return (s || "").toLocaleLowerCase("tr-TR")
      .replace(/ı/g, "i").replace(/ş/g, "s").replace(/ğ/g, "g")
      .replace(/ü/g, "u").replace(/ö/g, "o").replace(/ç/g, "c");
  }

  function matches(q, item) {
    var hay = norm(item.name + " " + item.code + " " + item.sectorName + " " + item.level);
    return hay.indexOf(norm(q)) !== -1;
  }

  function sectorName(slug) {
    var s = (window.MB_SECTORS || []).find(function (x) { return x.slug === slug; });
    return s ? s.name : slug;
  }

  function initSearch(root) {
    var input = root.querySelector('input[type="search"]');
    var panel = root.querySelector(".search-suggestions");
    var form = root.querySelector(".search-form");
    if (!input || !panel) return;

    var data = (window.MB_QUALIFICATIONS || []).map(function (q) {
      return Object.assign({}, q, { sectorName: sectorName(q.sector) });
    });

    var activeIndex = -1;

    function render(list) {
      if (!list.length) {
        panel.innerHTML = '<p class="sug-empty">Sonuç bulunamadı. Farklı bir meslek adı, MYK kodu veya sektör deneyin.</p>';
        panel.classList.add("is-open");
        return;
      }
      var html = "<ul>" + list.slice(0, 8).map(function (item, i) {
        return '<li><a href="yeterlilik.html?slug=' + encodeURIComponent(item.code) + '" data-index="' + i + '">' +
          '<span>' + item.name + ' <span class="sug-meta">Seviye ' + item.level + '</span></span>' +
          '<span class="sug-meta">' + item.code + ' · ' + item.sectorName + '</span></a></li>';
      }).join("") + "</ul>";
      panel.innerHTML = html;
      panel.classList.add("is-open");
      activeIndex = -1;
    }

    input.addEventListener("input", function () {
      var q = input.value.trim();
      if (!q) { panel.classList.remove("is-open"); panel.innerHTML = ""; return; }
      render(data.filter(function (item) { return matches(q, item); }));
    });

    input.addEventListener("keydown", function (e) {
      var links = panel.querySelectorAll("a");
      if (!links.length) return;
      if (e.key === "ArrowDown") {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, links.length - 1);
        links.forEach(function (l, i) { l.classList.toggle("is-active", i === activeIndex); });
        links[activeIndex].scrollIntoView({ block: "nearest" });
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        links.forEach(function (l, i) { l.classList.toggle("is-active", i === activeIndex); });
      } else if (e.key === "Enter" && activeIndex >= 0) {
        e.preventDefault();
        links[activeIndex].click();
      }
    });

    document.addEventListener("click", function (e) {
      if (!root.contains(e.target)) panel.classList.remove("is-open");
    });

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var q = input.value.trim();
        window.location.href = "meslekler.html" + (q ? "?q=" + encodeURIComponent(q) : "");
      });
    }
  }

  document.querySelectorAll("[data-search-widget]").forEach(initSearch);
})();
