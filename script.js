document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("[data-confirm]").forEach(function (el) {
    el.addEventListener("click", function (event) {
      if (!confirm(el.getAttribute("data-confirm"))) event.preventDefault();
    });
  });
  document.querySelectorAll(".auto-dismiss").forEach(function (el) {
    setTimeout(function(){ el.remove(); }, 4500);
  });
});
