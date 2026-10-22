<?php
$pageTitle = 'AI Shoe Finder';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.shoe-ai{max-width:1050px;margin:18px auto 40px;display:grid;grid-template-columns:minmax(0,1fr) 285px;gap:20px;align-items:start}
.ai-panel{border:1px solid #e6e8ee;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 10px 34px rgba(15,23,42,.06)}
.ai-panel-head{padding:22px 24px;background:#17191f;color:#fff;display:flex;align-items:center;gap:13px}
.ai-avatar{width:46px;height:46px;border-radius:15px;background:#184dc7;display:grid;place-items:center;font-size:23px;flex-shrink:0}
.ai-panel-head h1{font-size:23px;margin:0 0 3px}.ai-panel-head p{font-size:12px;color:#cbd5e1;margin:0}
.ai-messages{padding:20px;min-height:330px;max-height:590px;overflow-y:auto;background:#f8fafc;display:flex;flex-direction:column;gap:16px}
.ai-message{max-width:88%;border-radius:16px;padding:13px 15px;white-space:pre-wrap;line-height:1.55;font-size:14px;overflow-wrap:anywhere}
.ai-message.bot{align-self:flex-start;background:#fff;border:1px solid #e5e7eb;color:#20242c;border-top-left-radius:5px}
.ai-message.user{align-self:flex-end;background:#184dc7;color:#fff;border-top-right-radius:5px}
.ai-message .meta{font-size:11px;opacity:.7;display:block;margin-top:6px}
.ai-composer{padding:16px;border-top:1px solid #e8ebf0;background:#fff}
.ai-form{display:flex;gap:10px}.ai-form textarea{resize:vertical;min-height:52px;max-height:130px;flex:1;border:1px solid #d8dce5;border-radius:12px;padding:13px;font:inherit;font-size:14px;outline:none}.ai-form textarea:focus{border-color:#184dc7;box-shadow:0 0 0 3px #184dc71c}.ai-form button{border:0;border-radius:12px;background:#184dc7;color:#fff;padding:0 18px;font-weight:600;cursor:pointer}.ai-form button:disabled{opacity:.55;cursor:wait}
.ai-quick{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.ai-quick button{border:1px solid #dce1eb;background:#fff;color:#394150;border-radius:99px;padding:7px 11px;font-size:12px;cursor:pointer}.ai-quick button:hover{border-color:#184dc7;color:#184dc7}
.ai-results{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px;width:100%}.ai-product{background:#fff;border:1px solid #e5e7eb;border-radius:13px;overflow:hidden;min-width:0;color:#17191f}.ai-product img{width:100%;height:145px;object-fit:contain;background:#f5f6f8;padding:8px}.ai-product-body{padding:11px}.ai-product h3{font-size:13px;line-height:1.4;margin:0 0 5px}.ai-product .brand{font-size:12px;color:#737b8b;margin:0 0 7px}.ai-product .price{font-size:17px;color:#184dc7;font-weight:700;margin:0 0 8px}.ai-product .stock{font-size:11px;color:#657084;margin:0 0 10px}.ai-product a{display:block;text-align:center;border-radius:8px;background:#17191f;color:#fff;text-decoration:none;padding:9px;font-size:12px}
.ai-filter-tags{display:flex;gap:6px;flex-wrap:wrap}.ai-filter-tags span{font-size:11px;padding:5px 9px;border-radius:99px;background:#edf2ff;color:#184dc7}.ai-side{padding:20px}.ai-side h2{font-size:16px;margin:0 0 12px}.ai-side p,.ai-side li{font-size:13px;color:#687184;line-height:1.65}.ai-side ul{padding-left:19px;margin:10px 0}.ai-side .side-icon{font-size:24px;margin-bottom:10px}.ai-privacy{font-size:11px;color:#8a91a0;margin:12px 3px 0;line-height:1.6}
.ai-top-title{font-size:12px;font-weight:700;color:#184dc7;margin:12px 0 0;letter-spacing:.2px}
.ai-product{position:relative}.ai-rank{position:absolute;top:8px;left:8px;z-index:1;background:#184dc7;color:#fff;font-size:11px;font-weight:700;border-radius:99px;padding:3px 9px}
.ai-reason{background:#f1f6ff;border-left:3px solid #184dc7;border-radius:6px;padding:7px 9px;margin:0 0 10px;font-size:12px;line-height:1.55;color:#2b3a55}.ai-reason b{display:block;color:#184dc7;font-size:11px;margin-bottom:2px}
.ai-clear{margin-left:auto;border:1px solid #3a3f4b;background:transparent;color:#cbd5e1;border-radius:99px;padding:6px 12px;font-size:12px;cursor:pointer}.ai-clear:hover{background:#2a2e38;color:#fff}
.ai-ask{display:block;width:100%;margin:0 0 6px;border:1px solid #184dc7;background:#fff;color:#184dc7;border-radius:8px;padding:8px;font-size:12px;font-weight:600;cursor:pointer}.ai-ask:hover{background:#edf2ff}
.ai-focus{display:flex;align-items:center;gap:8px;margin:0 0 10px;padding:8px 10px;background:#edf2ff;border:1px solid #c9d8fb;border-radius:10px;font-size:12px;color:#184dc7}.ai-focus[hidden]{display:none}.ai-focus span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}.ai-focus button{border:0;background:transparent;color:#184dc7;font-size:14px;cursor:pointer;line-height:1}
.ai-follow{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}.ai-follow button{border:1px solid #c9d8fb;background:#fff;color:#184dc7;border-radius:99px;padding:6px 11px;font-size:12px;cursor:pointer}.ai-follow button:hover{background:#edf2ff}
.ai-answer-link{display:inline-block;margin-top:10px;font-size:12px;color:#184dc7;text-decoration:none;border:1px solid #c9d8fb;border-radius:8px;padding:6px 10px}
@media(max-width:800px){.shoe-ai{grid-template-columns:1fr}.ai-side{order:2}.ai-messages{min-height:300px;max-height:60vh}}
@media(max-width:480px){.ai-panel-head{padding:18px}.ai-messages{padding:13px}.ai-composer{padding:12px}.ai-form{align-items:stretch}.ai-form button{padding:0 13px}.ai-results{grid-template-columns:1fr;gap:10px}.ai-product img{height:115px}.ai-message{max-width:96%}}
</style>
<div class="shoe-ai">
  <section class="ai-panel" aria-label="AI Shoe Finder chat">
    <div class="ai-panel-head">
      <div class="ai-avatar" aria-hidden="true">👟</div>
      <div><h1>AI Shoe Finder</h1><p>ผู้ช่วยหารองเท้าที่ใช่สำหรับคุณบน StepUP</p></div>
      <button type="button" class="ai-clear" id="aiClear" hidden>🗑 ล้างแชท</button>
    </div>
    <div class="ai-messages" id="aiMessages" aria-live="polite">
      <div class="ai-message bot">สวัสดีครับ 👋 ผมช่วยหารองเท้าใน StepUP ให้ได้ บอกสไตล์ งบประมาณ แบรนด์ สี หรือไซซ์ที่ต้องการได้เลย เช่น “หารองเท้าวิ่งสีดำ ไซซ์ 42 งบไม่เกิน 2,000 บาท”</div>
    </div>
    <div class="ai-composer">
      <div class="ai-focus" id="aiFocus" hidden><span id="aiFocusText"></span><button type="button" id="aiFocusClear" aria-label="เลิกถามเกี่ยวกับสินค้านี้ และค้นหาใหม่" title="เลิกถามสินค้านี้ / ค้นหาใหม่">✕</button></div>
      <div class="ai-follow" id="aiFollow"></div>
      <form class="ai-form" id="aiForm">
        <textarea id="aiInput" maxlength="700" rows="2" placeholder="บอก AI ว่าคุณกำลังหารองเท้าแบบไหน..." aria-label="ความต้องการรองเท้า" required></textarea>
        <button id="aiSend" type="submit">ค้นหา ✦</button>
      </form>
      <div class="ai-quick" aria-label="ตัวอย่างคำค้น">
        <button type="button" data-prompt="หารองเท้าวิ่ง งบไม่เกิน 3000 บาท">🏃 รองเท้าวิ่ง</button>
        <button type="button" data-prompt="หารองเท้าสีดำ ไซซ์ 42">⚫ สีดำ ไซซ์ 42</button>
        <button type="button" data-prompt="หารองเท้า Nike งบไม่เกิน 2000 บาท">👟 Nike ไม่เกิน ฿2,000</button>
        <button type="button" data-prompt="หารองเท้าใส่ไปเรียนที่ใส่สบาย">✨ ใส่ไปเรียน</button>
      </div>
      <p class="ai-privacy">AI ค้นหาจากสินค้าที่เปิดขายและมีสต็อกใน StepUP เท่านั้น ตรวจสอบไซซ์ ราคา และรายละเอียดก่อนสั่งซื้อ</p>
    </div>
  </section>
  <aside class="ai-panel ai-side">
    <div class="side-icon">✨</div>
    <h2>ผู้ช่วยช้อปส่วนตัวของคุณ</h2>
    <p>คุยกับ AI ได้เหมือนถามพนักงานร้านรองเท้า แล้วดูสินค้าที่ตรงกับความต้องการได้ทันที</p>
    <ul>
      <li>ค้นหาตามงบประมาณและไซซ์</li>
      <li>เลือกแบรนด์ สี และประเภทการใช้งาน</li>
      <li>ดูสินค้าและราคาในระบบจริง</li>
      <li>แนะนำสินค้าทางเลือกเมื่อไม่พบรายการตรงเงื่อนไข</li>
    </ul>
    <p><strong>เคล็ดลับ:</strong> ยิ่งบอกงบ ไซซ์ และการใช้งานชัดเจน ผลการค้นหาก็ยิ่งตรงใจ</p>
  </aside>
</div>
<script>
(() => {
  const form = document.getElementById('aiForm');
  const input = document.getElementById('aiInput');
  const send = document.getElementById('aiSend');
  const messages = document.getElementById('aiMessages');
  const clearBtn = document.getElementById('aiClear');
  const focusBar = document.getElementById('aiFocus');
  const focusText = document.getElementById('aiFocusText');
  const followBox = document.getElementById('aiFollow');
  const defaultPlaceholder = input.placeholder;
  const FOCUS_KEY = 'stepup_ai_shoe_focus_v1';
  let focus = null, lastShown = [];
  try { const f = JSON.parse(sessionStorage.getItem(FOCUS_KEY) || 'null'); if (f && f.id) focus = f; } catch (e) {}
  const sendLabel = () => focus ? 'ถาม ✦' : 'ค้นหา ✦';
  function setFocus(f) {
    focus = f;
    try { f ? sessionStorage.setItem(FOCUS_KEY, JSON.stringify(f)) : sessionStorage.removeItem(FOCUS_KEY); } catch (e) {}
    focusBar.hidden = !f;
    if (f) {
      focusText.textContent = 'กำลังถามเกี่ยวกับ: ' + f.name;
      input.placeholder = 'ถามเกี่ยวกับ ' + f.name + ' เช่น มีไซซ์อะไรบ้าง เหมาะกับใช้งานแบบไหน…';
      followBox.innerHTML = ['มีไซซ์และสีอะไรบ้าง', 'เหมาะกับใช้งานแบบไหน', 'เทียบกับตัวอื่นที่แนะนำให้หน่อย'].map(q => `<button type="button" data-follow="${esc(q)}">${esc(q)}</button>`).join('');
    } else { input.placeholder = defaultPlaceholder; followBox.innerHTML = ''; }
    if (!send.disabled) send.textContent = sendLabel();
  }
  const STORE_KEY = 'stepup_ai_shoe_chat_v1';
  let chatLog = [];
  try { const saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || '[]'); if (Array.isArray(saved)) chatLog = saved; } catch (e) {}
  function remember(entry) {
    chatLog.push(entry); chatLog = chatLog.slice(-30);
    try { sessionStorage.setItem(STORE_KEY, JSON.stringify(chatLog)); } catch (e) {}
    clearBtn.hidden = !chatLog.length;
  }
  const endpoint = <?= json_encode(BASE_URL . '/actions/ai_shoe_finder.php', JSON_UNESCAPED_SLASHES) ?>;
  const baseUrl = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const money = value => new Intl.NumberFormat('th-TH', {style:'currency',currency:'THB',maximumFractionDigits:0}).format(Number(value) || 0);
  function addMessage(text, role, meta='') {
    const node = document.createElement('div');
    node.className = 'ai-message ' + role;
    node.textContent = text;
    if (meta) { const span = document.createElement('span'); span.className = 'meta'; span.textContent = meta; node.appendChild(span); }
    messages.appendChild(node); messages.scrollTop = messages.scrollHeight;
    return node;
  }
  function buildReason(p, filters) {
    if (p.reason && String(p.reason).trim()) return String(p.reason).trim();
    const reasons = [], tags = Array.isArray(filters) ? filters.map(String) : [];
    const sizes = String(p.sizes || '').split(',').map(x => x.trim());
    const price = Number(p.price) || 0, stock = Number(p.stock) || 0;
    tags.forEach(f => {
      const lf = f.toLowerCase();
      if (p.brand && lf.includes(String(p.brand).toLowerCase())) reasons.push('เป็นแบรนด์ ' + p.brand + ' ตามที่ต้องการ');
      else if (p.category && lf.includes(String(p.category).toLowerCase())) reasons.push('เป็นรองเท้าประเภท ' + p.category);
      const sz = f.match(/(?:ไซซ์|size)\s*:?\s*(\d{2}(?:\.\d)?)/i);
      if (sz && sizes.includes(sz[1])) reasons.push('มีไซซ์ ' + sz[1] + ' พร้อมขาย');
      const bd = f.replace(/,/g, '').match(/(\d{3,6})/);
      if (bd && /งบ|ไม่เกิน|บาท|฿|ราคา/.test(f) && !sz && price <= Number(bd[1])) reasons.push('ราคา ' + money(price) + ' อยู่ในงบ ' + money(bd[1]));
    });
    const uniq = [...new Set(reasons)];
    if (stock > 0 && stock <= 5) uniq.push('เหลือน้อย ' + stock + ' ชิ้น');
    return uniq.length ? uniq.join(' · ') : 'ใกล้เคียงเงื่อนไขของคุณที่สุดในสินค้าที่มีสต็อกตอนนี้';
  }
  function addProducts(products, parent, filters) {
    if (!Array.isArray(products) || !products.length) return;
    const top = products.slice(0, 3);
    const title = document.createElement('div'); title.className = 'ai-top-title';
    title.textContent = '✦ ' + top.length + ' อันดับที่ตรงใจที่สุด พร้อมเหตุผล'; parent.appendChild(title);
    const grid = document.createElement('div'); grid.className = 'ai-results';
    grid.innerHTML = top.map((p, i) => {
      const imageUrl = p.image ? (/^(https?:)?\/\//i.test(p.image) || p.image.startsWith('/') ? p.image : baseUrl + '/' + p.image.replace(/^\.\//, '')) : '';
      const img = imageUrl ? `<img src="${esc(imageUrl)}" alt="${esc(p.name)}" loading="lazy" onerror="this.style.display='none'">` : '<div style="height:145px;display:grid;place-items:center;background:#f5f6f8;font-size:38px">👟</div>';
      return `<article class="ai-product"><span class="ai-rank">#${i + 1}</span>${img}<div class="ai-product-body"><h3>${esc(p.name)}</h3><p class="brand">${esc(p.brand)}${p.category ? ' · '+esc(p.category):''}</p><p class="price">${money(p.price)}</p><div class="ai-reason"><b>ทำไมถึงเหมาะ</b>${esc(buildReason(p, filters))}</div><p class="stock">ไซซ์ ${esc(p.sizes || 'สอบถามร้าน')} · เหลือ ${Number(p.stock)||0} ชิ้น</p><button type="button" class="ai-ask" data-ask="${Number(p.id) || 0}" data-name="${esc(p.name)}">💬 ถามเกี่ยวกับรุ่นนี้</button><a href="${esc(p.url)}">ดูสินค้า</a></div></article>`;
    }).join('');
    parent.appendChild(grid); messages.scrollTop = messages.scrollHeight;
  }
  function renderBot(entry) {
    const node = addMessage(entry.text, 'bot', entry.meta || '');
    if (entry.filters && entry.filters.length) {
      const tags = document.createElement('div'); tags.className = 'ai-filter-tags';
      tags.innerHTML = entry.filters.map(x => `<span>${esc(x)}</span>`).join(''); node.appendChild(tags);
    }
    addProducts(entry.products, node, entry.filters);
    if (Array.isArray(entry.products) && entry.products.length) lastShown = entry.products.map(p => Number(p.id)).filter(Boolean);
    if (entry.link) { const a = document.createElement('a'); a.className = 'ai-answer-link'; a.href = entry.link; a.textContent = 'ดูหน้าสินค้า →'; node.appendChild(a); }
    return node;
  }
  async function submitMessage(text) {
    text = String(text || '').trim(); if (!text || send.disabled) return;
    const history = chatLog.slice(-6).map(e => ({role: e.role === 'bot' ? 'assistant' : 'user', text: String(e.text || '').slice(0, 400)}));
    const asking = !!focus;
    addMessage(text, 'user'); remember({role:'user', text}); input.value = ''; send.disabled = true; send.textContent = 'กำลังค้นหา…';
    const pending = addMessage(asking ? 'กำลังดูข้อมูลสินค้านี้ให้…' : 'กำลังทำความเข้าใจความต้องการและค้นหาสินค้าใน StepUP…', 'bot');
    try {
      const response = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({message:text, product_id:focus ? focus.id : 0, shown_ids:lastShown, history}), credentials:'same-origin'});
      const data = await response.json(); pending.remove();
      if (!response.ok || !data.success) throw new Error(data.message || 'ไม่สามารถค้นหาสินค้าได้ในขณะนี้');
      const entry = data.type === 'answer'
        ? {role:'bot', text:data.message || 'ไม่มีข้อมูลเพิ่มเติมในระบบ', meta:data.mode === 'ai' ? 'ตอบจากข้อมูลสินค้าในระบบ' : 'ข้อมูลจากระบบ', filters:[], products:[], link:data.url || ''}
        : {role:'bot', text:data.message || 'นี่คือสินค้าที่ค้นพบ', meta:data.mode === 'ai' ? 'AI assistant' : 'ค้นหาด้วยเงื่อนไขสินค้า', filters:Array.isArray(data.filters) ? data.filters : [], products:Array.isArray(data.products) ? data.products.slice(0, 3) : []};
      renderBot(entry); remember(entry);
    } catch (error) {
      pending.textContent = error.message || 'เกิดข้อผิดพลาด กรุณาลองอีกครั้ง';
    } finally { send.disabled = false; send.textContent = sendLabel(); input.focus(); }
  }
  chatLog.forEach(e => e.role === 'user' ? addMessage(e.text, 'user') : renderBot(e));
  clearBtn.hidden = !chatLog.length;
  clearBtn.addEventListener('click', () => {
    chatLog = []; lastShown = []; setFocus(null); try { sessionStorage.removeItem(STORE_KEY); } catch (e) {}
    messages.querySelectorAll('.ai-message').forEach((n, i) => { if (i > 0) n.remove(); });
    clearBtn.hidden = true;
  });
  form.addEventListener('submit', e => { e.preventDefault(); submitMessage(input.value); });
  document.querySelectorAll('[data-prompt]').forEach(button => button.addEventListener('click', () => { setFocus(null); submitMessage(button.dataset.prompt); }));
  messages.addEventListener('click', e => {
    const b = e.target.closest('[data-ask]'); if (!b) return;
    const id = Number(b.dataset.ask); if (!id) return;
    setFocus({id, name: b.dataset.name || 'สินค้านี้'}); input.focus();
  });
  followBox.addEventListener('click', e => { const b = e.target.closest('[data-follow]'); if (b) submitMessage(b.dataset.follow); });
  document.getElementById('aiFocusClear').addEventListener('click', () => { setFocus(null); input.focus(); });
  setFocus(focus);
  input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>