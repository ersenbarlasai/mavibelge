// Mavi Belge — tüm demo formlar için ortak davranış (veri gönderilmez)
(function () {
  var DEMO_MESSAGE = "Bu alan tanıtım sürümüdür. Gerçek başvuru işlemi yazılım aşamasında etkinleştirilecektir.";

  function showToast(msg) {
    var toast = document.querySelector(".mb-toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.className = "mb-toast";
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.classList.add("is-visible");
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toast.classList.remove("is-visible"); }, 4500);
  }

  function validateField(field) {
    var wrap = field.closest(".form-field");
    if (!wrap) return true;
    var valid = field.checkValidity();
    wrap.classList.toggle("is-invalid", !valid);
    return valid;
  }

  document.querySelectorAll("form[data-demo-form]").forEach(function (form) {
    var success = form.parentElement.querySelector(".form-success") || form.querySelector(".form-success");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var fields = form.querySelectorAll("input[required], select[required], textarea[required]");
      var allValid = true;
      fields.forEach(function (f) { if (!validateField(f)) allValid = false; });
      if (!allValid) {
        var firstInvalid = form.querySelector(".form-field.is-invalid input, .form-field.is-invalid select, .form-field.is-invalid textarea");
        if (firstInvalid) firstInvalid.focus();
        return;
      }
      form.reset();
      form.querySelectorAll(".form-field.is-invalid").forEach(function (w) { w.classList.remove("is-invalid"); });
      var fileList = form.querySelector(".file-list");
      if (fileList) fileList.innerHTML = "";
      if (success) {
        success.classList.add("is-visible");
        success.setAttribute("tabindex", "-1");
        success.focus();
      }
      showToast(DEMO_MESSAGE);
    });

    form.querySelectorAll("input, select, textarea").forEach(function (f) {
      f.addEventListener("blur", function () { validateField(f); });
    });
  });

  // Genel demo aksiyon butonları (form dışı — örn. "Sınav Sonucu Sorgula" gibi yer tutucu eylemler)
  document.querySelectorAll("[data-demo-action]").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      if (btn.tagName === "A" && btn.getAttribute("href") && btn.getAttribute("href") !== "#") return;
      e.preventDefault();
      showToast(DEMO_MESSAGE);
    });
  });

  // Dosya seçim arayüzü (gerçek gönderim yok)
  document.querySelectorAll('input[type="file"][data-file-demo]').forEach(function (input) {
    input.addEventListener("change", function () {
      var list = input.closest(".form-field").querySelector(".file-list");
      if (!list) return;
      list.innerHTML = "";
      Array.prototype.forEach.call(input.files, function (file) {
        var li = document.createElement("li");
        li.innerHTML = "<span>" + file.name + "</span><span>" + Math.round(file.size / 1024) + " KB</span>";
        list.appendChild(li);
      });
    });
  });

  // Çok adımlı form (online-basvuru.html)
  document.querySelectorAll("[data-step-form]").forEach(function (wrap) {
    var steps = Array.prototype.slice.call(wrap.querySelectorAll(".form-step"));
    var indicators = Array.prototype.slice.call(wrap.querySelectorAll(".step-indicator li"));
    var current = 0;

    function show(i) {
      steps.forEach(function (s, idx) { s.classList.toggle("is-active", idx === i); });
      indicators.forEach(function (ind, idx) {
        ind.classList.toggle("is-active", idx === i);
        ind.classList.toggle("is-done", idx < i);
      });
      current = i;
      wrap.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    wrap.querySelectorAll("[data-step-next]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var activeStep = steps[current];
        var required = activeStep.querySelectorAll("input[required], select[required], textarea[required]");
        var ok = true;
        required.forEach(function (f) { if (!validateField(f)) ok = false; });
        if (!ok) return;
        if (current < steps.length - 1) show(current + 1);
      });
    });
    wrap.querySelectorAll("[data-step-prev]").forEach(function (btn) {
      btn.addEventListener("click", function () { if (current > 0) show(current - 1); });
    });

    show(0);
  });
})();
