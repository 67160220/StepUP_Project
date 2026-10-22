// ยืนยันก่อนลบ
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
// ซ่อนข้อความแจ้งเตือนอัตโนมัติ
const alertBox = document.querySelector('.alert');
if (alertBox) setTimeout(() => alertBox.style.display = 'none', 4000);
