// MyPress — JS پنل مدیریت (محلی)
document.addEventListener('DOMContentLoaded', function () {
  // تایید حذف در همه فرم‌های حذف
  document.querySelectorAll('form[data-confirm], a[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (ev) {
      if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
    el.addEventListener('click', function (ev) {
      if (el.tagName === 'A' && !confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
  // ساخت خودکار اسلاگ از عنوان (فقط اگر اسلاگ خالی است)
  var title = document.querySelector('#title-field');
  var slug = document.querySelector('#slug-field');
  if (title && slug) title.addEventListener('input', function () {
    if (!slug.value) slug.value = title.value.trim().replace(/\s+/g, '-');
  });
});
