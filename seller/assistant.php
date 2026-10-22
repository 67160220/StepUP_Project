<?php
$pageTitle = 'ผู้ช่วยร้านค้า';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_seller();
require_once __DIR__ . '/../includes/header.php';
$storeName = (string)(current_store()['store_name'] ?? ($_SESSION['user']['shop_name'] ?? 'ร้านของฉัน'));
?>
<style>
.sa{max-width:860px;margin:6px auto 30px}
.sa-panel{border:1px solid #e6e8ee;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 10px 34px rgba(15,23,42,.06)}
.sa-head{padding:20px 24px;background:#17191f;color:#fff;display:flex;align-items:center;gap:13px}
.sa-avatar{width:46px;height:46px;border-radius:15px;background:#e52b3a;display:grid;place-items:center;font-size:23px;flex-shrink:0}
.sa-head h1{font-size:21px;margin:0 0 2px}.sa-head p{font-size:12px;color:#cbd5e1;margin:0}
.sa-clear{margin-left:auto;border:1px solid #3a3f4b;background:transparent;color:#cbd5e1;border-radius:99px;padding:6px 12px;font-size:12px;cursor:pointer}
.sa-clear:hover{background:#2a2e38;color:#fff}
.sa-msgs{padding:20px;min-height:340px;max-height:560px;overflow-y:auto;background:#f8fafc;display:flex;flex-direction:column;gap:14px}
.sa-msg{max-width:88%;border-radius:16px;padding:12px 15px;white-space:pre-wrap;line-height:1.6;font-size:14px;overflow-wrap:anywhere}
.sa-msg.bot{align-self:flex-start;background:#fff;border:1px solid #e5e7eb;border-top-left-radius:5px}
.sa-msg.user{align-self:flex-end;background:#17191f;color:#fff;border-top-right-radius:5px}
.sa-msg .meta{display:block;font-size:11px;opacity:.6;margin-top:6px}
.sa-comp{padding:16px;border-top:1px solid #e8ebf0;background:#fff}
.sa-form{display:flex;gap:10px}
.sa-form textarea{flex:1;resize:vertical;min-height:52px;max-height:130px;border:1px solid #d8dce5;border-radius:12px;padding:13px;font-size:14px;outline:none}
.sa-form textarea:focus{border-color:#17191f;box-shadow:0 0 0 3px #17191f1a}
.sa-form button{border:0;border-radius:12px;background:#17191f;color:#fff;padding:0 20px;font-weight:600;cursor:pointer}
.sa-form button:hover{background:#e52b3a}.sa-form button:disabled{opacity:.55;cursor:wait}
.sa-quick{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.sa-quick button{border:1px solid #dce1eb;background:#fff;color:#394150;border-radius:99px;padding:7px 12px;font-size:12px;cursor:pointer}
.sa-quick button:hover{border-color:#e52b3a;color:#e52b3a}
.sa-note{font-size:11px;color:#8a91a0;margin:12px 2px 0;line-height:1.6}
@media(max-width:600px){.sa-msg{max-width:96%}.sa-msgs{padding:13px}}
</style>

<div class="sa">
  <p style="margin:0 0 12px"><a href="index.php">← กลับแดชบอร์ด</a></p>
  <section class="sa-panel" aria-label="ผู้ช่วยร้านค้า">
    <div class="sa-head">
      <div class="sa-avatar" aria-hidden="true">🧑‍💼</div>
      <div><h1>ผู้ช่วยร้านค้า</h1><p>ถามเรื่องยอดขาย สต็อก ออเดอร์ และเงินพักของร้าน <?= e($storeName) ?></p></div>
      <button type="button" class="sa-clear" id="saClear" hidden>🗑 ล้างแชท</button>
    </div>
    <div class="sa-msgs" id="saMsgs" aria-live="polite">
      <div class="sa-msg bot">สวัสดีครับ 👋 ผมช่วยสรุปข้อมูลร้าน <?= e($storeName) ?> ให้ได้ เช่น ยอดขาย สินค้าขายดี สต็อกใกล้หมด ออเดอร์ที่ต้องส่ง และสถานะเงินพัก รวมถึงตอบวิธีใช้งานระบบ ลองถามได้เลยครับ</div>
    </div>
    <div class="sa-comp">
      <form class="sa-form" id="saForm">
        <textarea id="saInput" maxlength="600" rows="2" placeholder="ถามเกี่ยวกับร้านของคุณ..." aria-label="คำถามเกี่ยวกับร้าน" required></textarea>
        <button id="saSend" type="submit">ส่ง</button>
      </form>
      <div class="sa-quick">
        <button type="button" data-q="สรุปภาพรวมร้านวันนี้ให้หน่อย">📊 สรุปร้านวันนี้</button>
        <button type="button" data-q="ยอดขาย 7 วันล่าสุดเป็นยังไงบ้าง">💰 ยอดขาย 7 วัน</button>
        <button type="button" data-q="สินค้าตัวไหนสต็อกใกล้หมดหรือหมดแล้ว">📦 สต็อกใกล้หมด</button>
        <button type="button" data-q="มีออเดอร์ไหนต้องส่งของ และต้องส่งภายในเมื่อไร">🚚 ต้องส่งของ</button>
        <button type="button" data-q="เงินของร้านตอนนี้อยู่สถานะไหนบ้าง จะได้รับเมื่อไร">🏦 เงินพัก</button>
        <button type="button" data-q="สินค้าไหนขายดีที่สุด และควรเติมสต็อกอะไร">🔥 สินค้าขายดี</button>
        <button type="button" data-q="สินค้าไหนควรปรับปรุงรายละเอียดหรือรูปให้ขายง่ายขึ้น">✨ ปรับปรุงสินค้า</button>
      </div>
      <p class="sa-note">ผู้ช่วยตอบจากข้อมูลจริงของร้านคุณเท่านั้น ไม่เห็นชื่อ เบอร์ หรือที่อยู่ของผู้ซื้อ และไม่สามารถแก้ไขสินค้าหรือจัดส่งแทนได้ ตรวจสอบตัวเลขสำคัญที่หน้าคำสั่งซื้อก่อนตัดสินใจ</p>
    </div>
  </section>
</div>

<script>
(function () {
  const CSRF = <?= json_encode(csrf_token()) ?>;
  const API = <?= json_encode(BASE_URL . '/actions/seller_assistant.php') ?>;
  const box = document.getElementById('saMsgs'), form = document.getElementById('saForm');
  const input = document.getElementById('saInput'), send = document.getElementById('saSend');
  const clearBtn = document.getElementById('saClear');
  const intro = box.innerHTML;
  let history = [];

  function add(role, text, meta) {
    const d = document.createElement('div');
    d.className = 'sa-msg ' + (role === 'user' ? 'user' : 'bot');
    d.textContent = text;                       // textContent กัน XSS จากข้อความ AI
    if (meta) { const m = document.createElement('span'); m.className = 'meta'; m.textContent = meta; d.appendChild(m); }
    box.appendChild(d); box.scrollTop = box.scrollHeight;
    return d;
  }
  async function ask(text) {
    text = text.trim(); if (!text) return;
    add('user', text); input.value = ''; send.disabled = true; clearBtn.hidden = false;
    const wait = add('bot', 'กำลังดูข้อมูลร้าน…');
    try {
      const r = await fetch(API, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ message: text, history: history.slice(-6) })
      });
      const j = await r.json().catch(() => ({}));
      wait.remove();
      if (!r.ok || !j.success) { add('bot', j.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'); }
      else {
        add('bot', j.message, j.mode === 'ai' ? 'ตอบโดย AI จากข้อมูลร้านล่าสุด' : 'โหมดสำรอง (AI ไม่พร้อมใช้งาน) · ตอบจากข้อมูลร้านโดยตรง');
        history.push({ role: 'user', text }, { role: 'assistant', text: j.message });
      }
    } catch (e) { wait.remove(); add('bot', 'เชื่อมต่อไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'); }
    send.disabled = false; input.focus();
  }
  form.addEventListener('submit', e => { e.preventDefault(); ask(input.value); });
  input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } });
  document.querySelectorAll('.sa-quick button').forEach(b => b.addEventListener('click', () => ask(b.dataset.q)));
  clearBtn.addEventListener('click', () => { history = []; box.innerHTML = intro; clearBtn.hidden = true; });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
