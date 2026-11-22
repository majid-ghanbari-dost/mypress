// MyPress — JS سبد خرید (محلی)
// اعتبارسنجی ساده فرم تسویه قبل از ارسال.
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('form[action*="checkout"], form');
  if (!form) return;
  form.addEventListener('submit', function (ev) {
    var phone = form.querySelector('[name="customer_phone"]');
    if (phone && phone.value.trim().length < 10) {
      alert('شماره تلفن را کامل وارد کنید.');
      ev.preventDefault();
    }
  });
});
