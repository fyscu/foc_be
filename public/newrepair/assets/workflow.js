async function api(route, data = null, method = null) {
  const marker = route.indexOf('&');
  const routeName = marker === -1 ? route : route.slice(0, marker);
  const query = marker === -1 ? '' : route.slice(marker);
  const url = `api/index.php?route=${encodeURIComponent(routeName)}${query}`;
  const options = {};
  if (data !== null) {
    options.method = method || 'POST';
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify(data);
  } else if (method) {
    options.method = method;
  }
  try {
    const res = await fetch(url, options);
    const text = await res.text();
    let payload = null;
    try {
      payload = text ? JSON.parse(text) : null;
    } catch {
      payload = null;
    }

    if (payload && typeof payload === 'object') {
      return {
        success: Boolean(payload.success) && res.ok,
        data: payload.data ?? null,
        message: payload.message || (res.ok ? '' : `请求失败：HTTP ${res.status}`),
        status: res.status,
      };
    }

    return {
      success: false,
      data: { raw: text },
      message: text ? `服务器返回了无法识别的内容：${text.slice(0, 120)}` : `请求失败：HTTP ${res.status}`,
      status: res.status,
    };
  } catch (error) {
    return {
      success: false,
      data: null,
      message: error?.message ? `网络请求失败：${error.message}` : '网络请求失败，请检查连接后重试',
      status: 0,
    };
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[char]);
}

function appAlert(data) {
  const message = data?.message || (data?.success ? '操作成功' : '操作失败');
  if (!message) return;
  if (!window.fdxNotyf && window.Notyf) {
    window.fdxNotyf = new Notyf({
      duration: 3200,
      dismissible: true,
      ripple: false,
      position: { x: 'right', y: 'bottom' }
    });
  }
  if (window.fdxNotyf) {
    if (data?.success) window.fdxNotyf.success(message);
    else window.fdxNotyf.error(message);
    return;
  }
  window.alert(message);
}

function showMessage(id, data) {
  appAlert(data);
}

function showNotice(target, data) {
  appAlert(data);
}

function showGlobalMessage(data) {
  appAlert(data);
}

function setButtonBusy(button, busy, text = '处理中...') {
  if (!button) return () => {};
  if (busy) {
    if (!button.dataset.originalText) {
      button.dataset.originalText = button.textContent.trim();
    }
    button.disabled = true;
    button.classList.add('is-loading');
    button.textContent = text;
    return () => setButtonBusy(button, false);
  }
  button.disabled = false;
  button.classList.remove('is-loading');
  if (button.dataset.originalText) {
    button.textContent = button.dataset.originalText;
    delete button.dataset.originalText;
  }
  return () => {};
}

async function runWithButton(button, task, text = '处理中...') {
  const restore = setButtonBusy(button, true, text);
  try {
    return await task();
  } finally {
    restore();
  }
}

function formToPayload(form) {
  const payload = {};
  const multiNames = new Set();

  form.querySelectorAll('input[type="checkbox"][name$="[]"]').forEach((input) => {
    multiNames.add(input.name.slice(0, -2));
  });

  for (const [rawKey, value] of new FormData(form).entries()) {
    const isMulti = rawKey.endsWith('[]');
    const key = isMulti ? rawKey.slice(0, -2) : rawKey;
    if (isMulti || multiNames.has(key)) {
      if (!Array.isArray(payload[key])) payload[key] = [];
      payload[key].push(value);
    } else if (Object.prototype.hasOwnProperty.call(payload, key)) {
      payload[key] = Array.isArray(payload[key]) ? payload[key] : [payload[key]];
      payload[key].push(value);
    } else {
      payload[key] = value;
    }
  }

  form.querySelectorAll('input[type="checkbox"][name]:not([name$="[]"])').forEach((input) => {
    if (!Object.prototype.hasOwnProperty.call(payload, input.name)) {
      payload[input.name] = false;
    }
  });

  return payload;
}

function bindJsonForm(formId, route, messageId) {
  const form = document.getElementById(formId);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitter = event.submitter || form.querySelector('[type="submit"]');
    const data = await runWithButton(submitter, () => api(route, formToPayload(form)));
    showMessage(messageId, data);
    if (data.success) form.reset();
  });
}

async function loadNextNumber(position) {
  const result = await api(`orders.next_number&position=${encodeURIComponent(position)}`, null, 'GET');
  return result.success ? result.data.order_number : '';
}

async function refreshNumberPreview() {
  const odd = document.getElementById('nextOddNumber');
  const even = document.getElementById('nextEvenNumber');
  const selected = document.getElementById('position');
  const orderNumber = document.getElementById('orderNumber');

  const [oddNumber, evenNumber] = await Promise.all([
    loadNextNumber('1').catch(() => ''),
    loadNextNumber('4').catch(() => '')
  ]);

  if (odd) odd.textContent = oddNumber || '----';
  if (even) even.textContent = evenNumber || '----';
  if (selected && orderNumber && selected.value) {
    orderNumber.value = selected.value === '1' ? oddNumber : evenNumber;
  }
}

async function refreshSelectedOrderNumber() {
  const select = document.getElementById('position');
  const input = document.getElementById('orderNumber');
  if (!select || !input) return;
  input.value = select.value ? await loadNextNumber(select.value).catch(() => '') : '';
}

function bindOrderIntakeForm() {
  const form = document.getElementById('orderForm');
  const position = document.getElementById('position');
  const resetBtn = document.getElementById('resetForm');
  const submitNextBtn = document.getElementById('submitAndNext');
  const submitBtn = form.querySelector('[type="submit"]');
  let intakeSubmitting = false;

  function setIntakeSubmitting(value) {
    intakeSubmitting = value;
    [submitBtn, submitNextBtn, resetBtn].forEach((target) => {
      if (target) target.disabled = value;
    });
    if (submitBtn) submitBtn.textContent = value ? '提交中...' : '提交订单';
    if (submitNextBtn) submitNextBtn.textContent = value ? '提交中...' : '提交并添加下一个';
  }

  async function submit(addNext, button) {
    if (intakeSubmitting) return;
    if (!form.reportValidity()) return;
    const payload = formToPayload(form);
    setIntakeSubmitting(true);
    try {
      const result = await api('orders.create', payload);
      showMessage('message', result);

      if (!result.success) return;

      const keepPosition = payload.position || '';
      form.reset();
      if (addNext && position) {
        position.value = keepPosition;
        await refreshSelectedOrderNumber();
        form.querySelector('[name="customer_name"]')?.focus();
      } else {
        document.getElementById('orderNumber').value = '';
      }
      await refreshNumberPreview();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setIntakeSubmitting(false);
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    submit(false, event.submitter);
  });
  submitNextBtn?.addEventListener('click', () => submit(true, submitNextBtn));
  resetBtn?.addEventListener('click', async () => {
    form.reset();
    document.getElementById('orderNumber').value = '';
    await refreshNumberPreview();
  });
  position?.addEventListener('change', refreshSelectedOrderNumber);
  refreshNumberPreview();
}

function parseServiceTypes(value) {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return String(value).split(',').map((item) => item.trim()).filter(Boolean);
  }
}

function yesNo(value) {
  if (value === null || value === undefined || value === '') return '未指定';
  return Number(value) === 1 ? '是' : '否';
}

function statusLabel(status) {
  const labels = {
    waiting_assignment: '待技术员接机',
    assigned: '已分配',
    repairing: '维修中',
    ready_for_pickup: '待通知',
    notified: '待取机',
    completed: '已完成',
    cancelled: '已取消'
  };
  return labels[status] || '未知';
}

function formatClock(value = new Date()) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

function setText(id, value) {
  const target = document.getElementById(id);
  if (target) target.textContent = value;
}

function bindWorkbench() {
  document.getElementById('logoutBtn')?.addEventListener('click', async (event) => {
    const data = await runWithButton(event.currentTarget, () => api('staff.logout', {}), '退出中...');
    if (data.success) {
      location.href = 'index.html#/login';
      return;
    }
    showGlobalMessage(data);
  });
}

async function loadWorkbenchDashboard() {
  const result = await api('dashboard.stats', null, 'GET');
  if (!result.success) {
    showGlobalMessage(result);
    return;
  }

  const stats = result.data?.stats || {};
  const activity = result.data?.activity || null;
  const activityText = activity
    ? `${activity.name || '当前活动'} · ${activity.activity_date || ''}${activity.location ? ` · ${activity.location}` : ''}`
    : '未设置当前活动';
  setText('activityLine', activityText);

  const snapshot = document.getElementById('queueSnapshot');
  if (snapshot) {
    const items = [
      ['待分配', stats.waiting_assignment || 0],
      ['待维修', stats.assigned || 0],
      ['维修中', stats.repairing || 0],
      ['已完成', stats.completed || 0]
    ];
    snapshot.innerHTML = items.map(([label, count]) => `
      <div class="snapshot-item">
        <span>${escapeHtml(label)}</span>
        <strong>${Number(count)}</strong>
      </div>
    `).join('');
  }

  document.querySelectorAll('[data-count-for]').forEach((target) => {
    const key = target.dataset.countFor;
    target.textContent = Number(stats[key] || 0);
  });

  const tasks = document.getElementById('priorityTasks');
  if (tasks) {
    const rows = [
      ['待分配', stats.waiting_assignment || 0, '#/dispatch'],
      ['待维修', stats.assigned || 0, '#/dispatch'],
      ['维修中', stats.repairing || 0, '#/service'],
      ['已完成', stats.completed || 0, '#/admin/orders']
    ];
    tasks.innerHTML = rows.map(([label, count, href]) => `
      <a class="task-row" href="${escapeHtml(href)}">
        <span>${escapeHtml(label)}</span>
        <strong>${Number(count)} 单</strong>
      </a>
    `).join('');
  }
}

function orderSummary(order) {
  const serviceTypes = parseServiceTypes(order.service_types).join('、') || '未指定';
  return `
    <p>${escapeHtml(order.customer_name || '')} ${escapeHtml(order.customer_phone_mask || '')}</p>
    <p>${escapeHtml(order.device_type || '')} ${escapeHtml(order.device_model || '')}</p>
    <p>${escapeHtml(order.problem_description || '')}</p>
    <p>服务：${escapeHtml(serviceTypes)}</p>
    <p>确认过保：${Number(order.confirmed_out_of_warranty) === 1 ? '已确认' : '未确认'}</p>
  `;
}

function serviceTags(order) {
  const tags = parseServiceTypes(order.service_types);
  if (!tags.length) return '<span class="tag muted-tag">未选服务</span>';
  return tags.map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`).join('');
}

function queueSearchText(order) {
  return [
    order.order_no,
    order.customer_name,
    order.customer_phone,
    order.customer_phone_mask,
    order.device_type,
    order.device_model,
    order.problem_description,
    order.technician_name
  ].filter(Boolean).join(' ').toLowerCase();
}

function filterQueueOrders(orders) {
  const query = document.getElementById('orderSearch')?.value.trim().toLowerCase() || '';
  if (!query) return orders;
  return orders.filter((order) => queueSearchText(order).includes(query));
}

function updateQueueChrome(total, visible) {
  setText('queueCount', String(visible));
  setText('queueUpdated', `${visible}/${total} 单 · ${formatClock()} 更新`);
}

function bindQueueSearch(renderer) {
  const search = document.getElementById('orderSearch');
  if (!search) return;
  search.addEventListener('input', () => renderer());
}

function bindSignaturePads(root = document) {
  root.querySelectorAll('[data-signature-pad]').forEach((container) => {
    const canvas = container.querySelector('canvas');
    const input = container.querySelector('input[type="hidden"]');
    const clear = container.querySelector('[data-signature-clear]');
    if (!canvas || !input) return;
    if (!canvas.hasAttribute('tabindex')) canvas.tabIndex = 0;

    const ctx = canvas.getContext('2d');
    let drawing = false;
    let signed = Boolean(input.value);

    const emitSignatureChange = () => {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const redraw = () => {
      if (!input.value) return;
      const image = new Image();
      image.onload = () => {
        const width = canvas.width / (window.devicePixelRatio || 1);
        const height = canvas.height / (window.devicePixelRatio || 1);
        if (!width || !height) return;
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(image, 0, 0, width, height);
      };
      image.src = input.value;
    };

    const resize = () => {
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const ratio = window.devicePixelRatio || 1;
      canvas.width = Math.floor(rect.width * ratio);
      canvas.height = Math.floor(rect.height * ratio);
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.lineWidth = 2.4;
      ctx.strokeStyle = '#172033';
      redraw();
    };

    if (!container.dataset.signatureBound) {
      const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
          x: event.clientX - rect.left,
          y: event.clientY - rect.top
        };
      };
      const finish = () => {
        if (!drawing) return;
        drawing = false;
        if (signed) {
          input.value = canvas.toDataURL('image/png');
          emitSignatureChange();
        }
      };

      canvas.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        canvas.focus();
        canvas.setPointerCapture?.(event.pointerId);
        const p = point(event);
        drawing = true;
        signed = true;
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
      });
      canvas.addEventListener('pointermove', (event) => {
        if (!drawing) return;
        event.preventDefault();
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
      });
      canvas.addEventListener('pointerup', finish);
      canvas.addEventListener('pointercancel', finish);
      clear?.addEventListener('click', () => {
        resize();
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
        signed = false;
        emitSignatureChange();
      });
      container.dataset.signatureBound = '1';
    }

    window.setTimeout(resize, 0);
  });
}

function requireSignature(form, messageId, field = 'technician_signature') {
  const input = form.querySelector(`[name="${field}"]`);
  if (input?.value) return true;
  const data = { success: false, message: field === 'customer_signature' ? '请先完成机主签字' : '请先完成技术员签字' };
  if (messageId) showMessage(messageId, data);
  else showGlobalMessage(data);
  return false;
}

function queueCard(order, actions) {
  const owner = `${order.customer_name || ''} ${order.customer_phone_mask || ''}`.trim() || '未填机主';
  const device = `${order.device_type || ''} ${order.device_model || ''}`.trim() || '未填设备';
  return `
    <article class="queue-card">
      <div class="queue-card-main">
        <div class="order-heading">
          <strong>${escapeHtml(order.order_no)}</strong>
          <span class="status-pill">${escapeHtml(statusLabel(order.status))}</span>
        </div>
        <div class="queue-meta">
          <span>${escapeHtml(owner)}</span>
          <span>${escapeHtml(device)}</span>
          <span>${escapeHtml(order.created_at || '')}</span>
        </div>
        <div class="tag-row">${serviceTags(order)}</div>
        <p class="queue-problem">${escapeHtml(order.problem_description || '未填故障描述')}</p>
      </div>
      <div class="queue-actions">${actions}</div>
    </article>
  `;
}

const dispatchState = {
  orders: [],
  technicians: []
};

async function loadDispatchOrders() {
  const target = document.getElementById('orders');
  if (!target) return;
  const statuses = ['waiting_assignment', 'assigned', 'repairing'];
  const [techResult, ...orderResults] = await Promise.all([
    api('technicians.list', null, 'GET'),
    ...statuses.map((status) => api(`orders.list&status=${encodeURIComponent(status)}`, null, 'GET'))
  ]);
  const failed = orderResults.find((result) => !result.success);
  if (failed) {
    target.innerHTML = `<div class="notice danger">${escapeHtml(failed.message)}</div>`;
    return;
  }
  dispatchState.technicians = techResult.success ? (techResult.data.technicians || []) : [];
  dispatchState.orders = orderResults.flatMap((result) => result.data.orders || []);
  renderDispatchOrders();
}

function renderDispatchOrders() {
  const target = document.getElementById('orders');
  if (!target) return;
  const orders = filterQueueOrders(dispatchState.orders);
  updateQueueChrome(dispatchState.orders.length, orders.length);
  const options = [
    '<option value="">选择技术员</option>',
    ...dispatchState.technicians.map((t) => `<option value="${Number(t.id)}">${escapeHtml(t.display_name)}</option>`)
  ].join('');
  target.innerHTML = orders.length ? orders.map((order) => {
    const actionLabel = order.status === 'waiting_assignment' ? '分配' : '转单';
    const currentTech = order.technician_name ? `<div class="assigned-tech">当前：${escapeHtml(order.technician_name)}</div>` : '';
    const claimButton = order.status === 'waiting_assignment'
      ? `<button class="button" data-claim-for="${Number(order.id)}" onclick="showClaimQr(${Number(order.id)})">二维码</button>`
      : '';
    return queueCard(order, `
      ${currentTech}
      <select data-tech-for="${Number(order.id)}">${options}</select>
      <button class="button primary" data-assign-for="${Number(order.id)}" onclick="assignOrder(${Number(order.id)})">${actionLabel}</button>
      ${claimButton}
    `);
  }).join('') : '<div class="empty">暂无领取或转单任务</div>';
}

async function assignOrder(orderId) {
  const select = document.querySelector(`[data-tech-for="${orderId}"]`);
  const button = document.querySelector(`[data-assign-for="${orderId}"]`);
  if (!select?.value) {
    showGlobalMessage({ success: false, message: '请先选择技术员' });
    select?.focus();
    return;
  }
  const data = await runWithButton(button, () => api('orders.assign', { order_id: orderId, technician_id: select.value }), '分配中...');
  if (data.success) loadDispatchOrders();
  else showGlobalMessage(data);
}

async function showClaimQr(orderId) {
  const button = document.querySelector(`[data-claim-for="${orderId}"]`);
  const data = await runWithButton(button, () => api('orders.claim_link', { order_id: orderId }), '生成中...');
  if (!data.success) {
    showGlobalMessage(data);
    return;
  }

  const modal = document.getElementById('claimModal');
  const qrTarget = document.getElementById('claimQr');
  const urlInput = document.getElementById('claimUrl');
  if (!modal || !qrTarget || !urlInput) return;

  qrTarget.innerHTML = '';
  urlInput.value = data.data.url;
  if (window.QRCode) {
    new QRCode(qrTarget, {
      text: data.data.url,
      width: 220,
      height: 220,
      colorDark: '#172033',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  } else {
    qrTarget.textContent = data.data.url;
  }
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  loadPickupOrders();
}

function closeClaimModal() {
  const modal = document.getElementById('claimModal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.setAttribute('aria-hidden', 'true');
}

const pickupState = {
  orders: []
};

async function loadPickupOrders() {
  const target = document.getElementById('orders');
  if (!target) return;
  const statuses = ['ready_for_pickup', 'notified', 'repairing'];
  const results = await Promise.all(statuses.map((status) => api(`orders.list&status=${encodeURIComponent(status)}`, null, 'GET')));
  const failed = results.find((result) => !result.success);
  if (failed) {
    target.innerHTML = `<div class="notice danger">${escapeHtml(failed.message)}</div>`;
    return;
  }
  pickupState.orders = results.flatMap((result) => result.data.orders || []);
  renderPickupOrders();
}

function renderPickupOrders() {
  const target = document.getElementById('orders');
  if (!target) return;
  const orders = filterQueueOrders(pickupState.orders);
  updateQueueChrome(pickupState.orders.length, orders.length);
  target.innerHTML = orders.length ? orders.map((order) => {
    const techLine = `<div class="assigned-tech">技术员：${escapeHtml(order.technician_name || '未分配')}</div>`;
    const actions = order.status === 'ready_for_pickup'
      ? `
        ${techLine}
        <button class="button" data-sms-for="${Number(order.id)}" onclick="showSmsNotice(${Number(order.id)})">短信通知</button>
        <button class="button primary" data-pickup-for="${Number(order.id)}" data-order-no="${escapeHtml(order.order_no)}" onclick="openPickupModal(${Number(order.id)})">确认取机</button>
      `
      : order.status === 'notified'
      ? `
        ${techLine}
        <button class="button primary" data-pickup-for="${Number(order.id)}" data-order-no="${escapeHtml(order.order_no)}" onclick="openPickupModal(${Number(order.id)})">确认取机</button>
      `
      : `
        ${techLine}
        <button class="button primary" data-complete-for="${Number(order.id)}" onclick="openStaffCompleteModal(${Number(order.id)})">登记修完</button>
      `;
    return queueCard(order, actions);
  }).join('') : '<div class="empty">暂无维修或取机任务</div>';
}

function openStaffCompleteModal(orderId) {
  const modal = document.getElementById('repairCompleteModal');
  const form = document.getElementById('repairCompleteForm');
  if (!modal || !form) return;
  form.reset();
  form.order_id.value = orderId;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  bindSignaturePads(modal);
}

function closeStaffCompleteModal() {
  const modal = document.getElementById('repairCompleteModal');
  modal?.classList.add('hidden');
  modal?.setAttribute('aria-hidden', 'true');
}

function openPickupModal(orderId) {
  const modal = document.getElementById('pickupModal');
  const form = document.getElementById('pickupForm');
  if (!modal || !form) return;
  form.reset();
  form.order_id.value = orderId;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  bindSignaturePads(modal);
}

function closePickupModal() {
  const modal = document.getElementById('pickupModal');
  modal?.classList.add('hidden');
  modal?.setAttribute('aria-hidden', 'true');
}

async function submitStaffComplete(event) {
  event.preventDefault();
  if (!requireSignature(event.currentTarget)) return;
  const button = event.submitter || event.currentTarget.querySelector('[type="submit"]');
  const data = await runWithButton(button, () => api('orders.staff_complete_repair', formToPayload(event.currentTarget)), '登记中...');
  showGlobalMessage(data);
  if (data.success) {
    closeStaffCompleteModal();
    loadPickupOrders();
  }
}

async function submitPickup(event) {
  event.preventDefault();
  if (!requireSignature(event.currentTarget, null, 'customer_signature')) return;
  const button = event.submitter || event.currentTarget.querySelector('[type="submit"]');
  const data = await runWithButton(button, () => api('orders.pickup', formToPayload(event.currentTarget)), '确认中...');
  showGlobalMessage(data);
  if (data.success) {
    closePickupModal();
    loadPickupOrders();
  }
}

async function showSmsNotice(orderId) {
  const button = document.querySelector(`[data-sms-for="${orderId}"]`);
  const data = await runWithButton(button, () => api('orders.sms_notice', { order_id: orderId }), '生成中...');
  if (!data.success) {
    showGlobalMessage(data);
    return;
  }
  const modal = document.getElementById('smsModal');
  const qrTarget = document.getElementById('smsQr');
  const content = document.getElementById('smsContent');
  const url = document.getElementById('smsUrl');
  if (!modal || !qrTarget || !content || !url) return;
  qrTarget.innerHTML = '';
  content.textContent = data.data.content || '';
  url.value = data.data.url || '';
  if (window.QRCode) {
    new QRCode(qrTarget, {
      text: data.data.url,
      width: 220,
      height: 220,
      colorDark: '#172033',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  } else {
    qrTarget.textContent = data.data.url || '';
  }
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
}

function closeSmsModal() {
  const modal = document.getElementById('smsModal');
  modal?.classList.add('hidden');
  modal?.setAttribute('aria-hidden', 'true');
}

function startAutoRefresh(loader, interval = 6000) {
  window.setInterval(() => {
    if (document.hidden) return;
    loader();
  }, interval);
}

document.addEventListener('click', (event) => {
  const target = event.target.closest('.button, .admin-tab, .status-tab, a.flow-step, .task-row, a.admin-row-main, button.admin-row-main, .tech-picker-item, .tech-order-card');
  if (!target || target.disabled || target.getAttribute('aria-disabled') === 'true' || target.classList.contains('disabled')) return;
  target.classList.remove('is-pressing');
  void target.offsetWidth;
  target.classList.add('is-pressing');
  window.setTimeout(() => target.classList.remove('is-pressing'), 160);
}, true);
