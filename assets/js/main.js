// MyPress — JS عمومی سایت (محلی، بدون CDN)
// تایید حذف و بهبود جزئی فرم‌ها؛ بدون وابستگی خارجی.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
});
