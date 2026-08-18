// Mavi Belge — meslek listesi, ücret tablosu ve haber filtreleri
(function () {
  function norm(s) {
    return (s || "").toLocaleLowerCase("tr-TR")
      .replace(/ı/g, "i").replace(/ş/g, "s").replace(/ğ/g, "g")
      .replace(/ü/g, "u").replace(/ö/g, "o").replace(/ç/g, "c");
  }
  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function sectorName(slug) {
    var s = (window.MB_SECTORS || []).find(function (x) { return x.slug === slug; });
    return s ? s.name : slug;
  }

  // ---- Meslekler listesi (meslekler.html) ----
  var qualList = document.getElementById("qual-list");
  if (qualList) {
    var data = (window.MB_QUALIFICATIONS || []);
    var sectorSel = document.getElementById("filter-sector");
    var levelSel = document.getElementById("filter-level");
    var searchInput = document.getElementById("filter-search");
    var countEl = document.getElementById("qual-count");
    var paginationEl = document.getElementById("qual-pagination");
    var currentPage = 1;
    var mobileQuery = window.matchMedia("(max-width: 768px)");
    function pageSize() { return mobileQuery.matches ? 8 : 12; }

    if (sectorSel) {
      (window.MB_SECTORS || []).forEach(function (s) {
        var o = document.createElement("option");
        o.value = s.slug; o.textContent = s.name;
        sectorSel.appendChild(o);
      });
    }

    function renderList() {
      var q = searchInput ? norm(searchInput.value) : "";
      var sec = sectorSel ? sectorSel.value : "";
      var lvl = levelSel ? levelSel.value : "";
      var filtered = data.filter(function (item) {
        var hay = norm(item.name + " " + item.code + " " + sectorName(item.sector));
        var okQ = !q || hay.indexOf(q) !== -1;
        var okSec = !sec || item.sector === sec;
        var okLvl = !lvl || String(item.level) === lvl;
        return okQ && okSec && okLvl;
      });

      var total = filtered.length;
      var ps = pageSize();
      var totalPages = Math.max(1, Math.ceil(total / ps));
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;
      var start = (currentPage - 1) * ps;
      var pageItems = filtered.slice(start, start + ps);

      qualList.innerHTML = pageItems.map(function (item) {
        return '<div class="qual-card">' +
          '<div class="qual-info"><strong>' + item.name + '</strong>' +
          '<div class="qual-tags"><span class="tag">' + item.code + '</span><span class="tag">Seviye ' + item.level + '</span><span class="tag">' + sectorName(item.sector) + '</span></div></div>' +
          '<div class="qual-actions">' +
          '<a class="btn btn-secondary btn-sm" href="yeterlilik.html?slug=' + encodeURIComponent(item.code) + '">Detayları Gör</a>' +
          '<a class="btn btn-primary btn-sm" href="online-basvuru.html?meslek=' + encodeURIComponent(item.code) + '">Başvuru Yap</a>' +
          '</div></div>';
      }).join("") || '<p class="sug-empty">Aramanızla eşleşen yeterlilik bulunamadı.</p>';

      if (countEl) {
        if (total === 0) {
          countEl.textContent = "Aramanızla eşleşen yeterlilik bulunamadı.";
        } else {
          countEl.textContent = "Toplam " + total + " meslekten " + (start + 1) + "–" + Math.min(start + ps, total) + " arası gösteriliyor.";
        }
      }

      renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
      if (!paginationEl) return;
      if (totalPages <= 1) { paginationEl.innerHTML = ""; return; }
      var html = '<button type="button" class="page-btn" data-page="' + (currentPage - 1) + '" ' +
        (currentPage === 1 ? "disabled" : "") + ' aria-label="Önceki sayfa">Önceki</button>';
      for (var p = 1; p <= totalPages; p++) {
        html += '<button type="button" class="page-btn' + (p === currentPage ? " is-active" : "") + '" data-page="' + p + '" ' +
          (p === currentPage ? 'aria-current="page"' : "") + ' aria-label="Sayfa ' + p + '">' + p + '</button>';
      }
      html += '<button type="button" class="page-btn" data-page="' + (currentPage + 1) + '" ' +
        (currentPage === totalPages ? "disabled" : "") + ' aria-label="Sonraki sayfa">Sonraki</button>';
      paginationEl.innerHTML = html;
    }

    if (paginationEl) {
      paginationEl.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-page]");
        if (!btn || btn.disabled) return;
        currentPage = parseInt(btn.getAttribute("data-page"), 10);
        renderList();
        qualList.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }

    var initialQ = qs("q");
    var initialSector = qs("sektor") || qs("sector");
    if (initialQ && searchInput) searchInput.value = initialQ;
    if (initialSector && sectorSel) sectorSel.value = initialSector;

    [searchInput, sectorSel, levelSel].forEach(function (el) {
      if (el) el.addEventListener("input", function () { currentPage = 1; renderList(); });
    });
    if (mobileQuery.addEventListener) {
      mobileQuery.addEventListener("change", function () { currentPage = 1; renderList(); });
    }
    renderList();
  }

  // ---- Sektör sayfası (sektor.html?slug=...) ----
  var sectorPage = document.getElementById("sector-page");
  if (sectorPage) {
    var slug = qs("slug") || "makine";
    var sector = (window.MB_SECTORS || []).find(function (s) { return s.slug === slug; });
    var items = (window.MB_QUALIFICATIONS || []).filter(function (q) { return q.sector === slug; });

    if (sector) {
      document.querySelectorAll("[data-sector-name]").forEach(function (el) { el.textContent = sector.name; });
      document.querySelectorAll("[data-sector-desc]").forEach(function (el) { el.textContent = sector.desc; });
      document.title = sector.name + " Meslekleri — Mavi Belge";
      if (sector.image) { sectorPage.style.backgroundImage = "url('" + sector.image + "')"; }
    }
    var list = document.getElementById("sector-qual-list");
    if (list) {
      list.innerHTML = items.map(function (item) {
        return '<div class="qual-card">' +
          '<div class="qual-info"><strong>' + item.name + '</strong>' +
          '<div class="qual-tags"><span class="tag">' + item.code + '</span><span class="tag">Seviye ' + item.level + '</span></div></div>' +
          '<div class="qual-actions">' +
          '<a class="btn btn-secondary btn-sm" href="yeterlilik.html?slug=' + encodeURIComponent(item.code) + '">Detayları Gör</a>' +
          '<a class="btn btn-primary btn-sm" href="online-basvuru.html?meslek=' + encodeURIComponent(item.code) + '">Başvuru Yap</a>' +
          '</div></div>';
      }).join("") || '<p>Bu sektör için yeterlilik bilgisi güncellenecektir.</p>';
    }

    // Sector switch tabs
    var tabs = document.getElementById("sector-tabs");
    if (tabs) {
      tabs.innerHTML = (window.MB_SECTORS || []).map(function (s) {
        return '<a class="tag' + (s.slug === slug ? ' is-active' : '') + '" href="sektor.html?slug=' + s.slug + '">' + s.name + '</a>';
      }).join(" ");
    }
  }

  // ---- Yeterlilik detay (yeterlilik.html?slug=CODE) ----
  var qualDetail = document.getElementById("qual-detail");
  if (qualDetail) {
    var code = qs("slug");
    var item = code ? (window.MB_QUALIFICATIONS || []).find(function (q) { return q.code === code; }) : null;
    var notFoundEl = document.getElementById("qual-not-found");
    var foundEl = document.getElementById("qual-found");
    var notFoundBody = document.getElementById("qual-not-found-body");
    var foundBody = document.getElementById("qual-found-body");
    if (item) {
      if (notFoundEl) notFoundEl.style.display = "none";
      if (foundEl) foundEl.style.display = "";
      if (notFoundBody) notFoundBody.style.display = "none";
      if (foundBody) foundBody.style.display = "";
      var sec = (window.MB_SECTORS || []).find(function (s) { return s.slug === item.sector; });
      document.querySelectorAll("[data-q-name]").forEach(function (el) { el.textContent = item.name; });
      document.querySelectorAll("[data-q-code]").forEach(function (el) { el.textContent = item.code; });
      document.querySelectorAll("[data-q-level]").forEach(function (el) { el.textContent = item.level; });
      document.querySelectorAll("[data-q-sector]").forEach(function (el) { el.textContent = sec ? sec.name : item.sector; });
      document.querySelectorAll("[data-q-sector-link]").forEach(function (el) { el.setAttribute("href", "sektor.html?slug=" + item.sector); });
      document.querySelectorAll("[data-q-apply-link]").forEach(function (el) { el.setAttribute("href", "online-basvuru.html?meslek=" + encodeURIComponent(item.code)); });
      document.title = item.name + " (" + item.code + ") — Mavi Belge";
      if (sec && sec.image) { qualDetail.style.backgroundImage = "url('" + sec.image + "')"; }
    } else {
      if (foundEl) foundEl.style.display = "none";
      if (notFoundEl) notFoundEl.style.display = "";
      if (foundBody) foundBody.style.display = "none";
      if (notFoundBody) notFoundBody.style.display = "";
      document.title = "Yeterlilik Bulunamadı — Mavi Belge";
    }
  }

  // ---- Ücretler (sinav-ucretleri.html) ----
  var feeTableBody = document.getElementById("fee-table-body");
  if (feeTableBody) {
    var feeCards = document.getElementById("fee-cards");
    var feeSector = document.getElementById("fee-filter-sector");
    if (feeSector) {
      (window.MB_SECTORS || []).forEach(function (s) {
        var o = document.createElement("option");
        o.value = s.slug; o.textContent = s.name;
        feeSector.appendChild(o);
      });
    }
    function renderFees() {
      var sec = feeSector ? feeSector.value : "";
      var rows = (window.MB_QUALIFICATIONS || []).filter(function (q) { return !sec || q.sector === sec; });
      feeTableBody.innerHTML = rows.map(function (q) {
        return "<tr><td>" + q.name + "</td><td>" + q.code + "</td><td>Seviye " + q.level + "</td><td>" + sectorName(q.sector) + "</td><td>Bilgi güncellenecektir</td></tr>";
      }).join("");
      if (feeCards) {
        feeCards.innerHTML = rows.map(function (q) {
          return '<div class="fee-card">' +
            '<div class="fee-card-row"><span class="fee-card-label">Meslek</span><span class="fee-card-value">' + q.name + '</span></div>' +
            '<div class="fee-card-row"><span class="fee-card-label">MYK Kodu</span><span class="fee-card-value">' + q.code + '</span></div>' +
            '<div class="fee-card-row"><span class="fee-card-label">Seviye</span><span class="fee-card-value">' + q.level + '</span></div>' +
            '<div class="fee-card-row"><span class="fee-card-label">Sektör</span><span class="fee-card-value">' + sectorName(q.sector) + '</span></div>' +
            '<div class="fee-card-row"><span class="fee-card-label">Ücret</span><span class="fee-card-value fee-card-price">Bilgi güncellenecektir</span></div>' +
            '</div>';
        }).join("") || '<p class="sug-empty">Aramanızla eşleşen ücret kaydı bulunamadı.</p>';
      }
    }
    if (feeSector) feeSector.addEventListener("change", renderFees);
    renderFees();
  }

  // ---- Haberler (haberler.html) ----
  var newsList = document.getElementById("news-list");
  if (newsList) {
    var newsSearch = document.getElementById("news-search");
    var newsType = document.getElementById("news-type");
    function renderNews() {
      var q = newsSearch ? norm(newsSearch.value) : "";
      var type = newsType ? newsType.value : "";
      var items = (window.MB_NEWS || []).filter(function (n) {
        var okQ = !q || norm(n.title + " " + n.summary).indexOf(q) !== -1;
        var okType = !type || n.type === type;
        return okQ && okType;
      }).sort(function (a, b) { return b.date.localeCompare(a.date); });
      newsList.innerHTML = items.map(function (n, i) {
        return '<article class="news-card"><img src="' + n.image + '" alt="" loading="' + (i < 3 ? "eager" : "lazy") + '">' +
          '<div class="news-body"><span class="badge ' + (n.type === "duyuru" ? "badge-duyuru" : "") + '">' + (n.type === "duyuru" ? "Duyuru" : "Haber") + '</span>' +
          '<h3>' + n.title + '</h3><div class="news-meta"><time datetime="' + n.date + '">' + formatDate(n.date) + '</time></div>' +
          '<p>' + n.summary + '</p>' +
          '<a class="task-link" href="haber-detay.html?slug=' + n.slug + '">Haberi Oku →</a></div></article>';
      }).join("") || "<p>Aramanızla eşleşen içerik bulunamadı.</p>";
    }
    function formatDate(iso) {
      var d = new Date(iso + "T00:00:00");
      return d.toLocaleDateString("tr-TR", { day: "2-digit", month: "long", year: "numeric" });
    }
    window.mbFormatDate = formatDate;
    if (newsSearch) newsSearch.addEventListener("input", renderNews);
    if (newsType) newsType.addEventListener("change", renderNews);
    renderNews();
  }

  // ---- Haber detay ----
  var newsDetail = document.getElementById("news-detail");
  if (newsDetail) {
    var nslug = qs("slug");
    var n = (window.MB_NEWS || []).find(function (x) { return x.slug === nslug; }) || (window.MB_NEWS || [])[0];
    if (n) {
      document.querySelectorAll("[data-n-title]").forEach(function (el) { el.textContent = n.title; });
      document.querySelectorAll("[data-n-body]").forEach(function (el) { el.textContent = n.body; });
      document.querySelectorAll("[data-n-summary]").forEach(function (el) { el.textContent = n.summary; });
      document.querySelectorAll("[data-n-image]").forEach(function (el) { el.setAttribute("src", n.image); });
      document.querySelectorAll("[data-n-date]").forEach(function (el) {
        var d = new Date(n.date + "T00:00:00");
        el.textContent = d.toLocaleDateString("tr-TR", { day: "2-digit", month: "long", year: "numeric" });
        el.setAttribute("datetime", n.date);
      });
      document.querySelectorAll("[data-n-type]").forEach(function (el) { el.textContent = n.type === "duyuru" ? "Duyuru" : "Haber"; });
      document.title = n.title + " — Mavi Belge";
    }
  }
})();
