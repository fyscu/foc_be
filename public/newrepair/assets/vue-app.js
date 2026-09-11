(function () {
  const boot = window.FDX_BOOT || {};
  const { createApp, nextTick } = Vue;
  const APP_BASE = String(boot.base || '/public/newrepair').replace(/\/+$/, '');

  const routeMap = {
    '': 'home',
    home: 'home',
    query: 'query',
    login: 'login',
    'admin-login': 'adminLogin',
    admin: 'admin',
    workbench: 'workbench',
    intake: 'intake',
    dispatch: 'dispatch',
    service: 'service',
    tech: 'tech',
    'wechat-open': 'wechatOpen'
  };
  const adminTabs = ['orders', 'settings', 'shift', 'technicians'];

  function normalizeAdminTab(tab) {
    return adminTabs.includes(tab) ? tab : 'orders';
  }

  function routeFromHash() {
    const rawHash = window.location.hash ? window.location.hash.slice(1) : '/';
    const rawRoute = rawHash.startsWith('/') ? rawHash : `/${rawHash}`;
    const [pathPart, queryString = ''] = rawRoute.split('?');
    const slug = pathPart.replace(/^\/+|\/+$/g, '');
    const segments = slug.split('/').filter(Boolean);
    const query = new URLSearchParams(queryString);
    const params = {};
    for (const [key, value] of query.entries()) params[key] = value;
    const page = routeMap[slug] || (segments[0] === 'admin' ? 'admin' : 'home');
    return {
      page,
      slug,
      path: `/${slug}`,
      adminTab: page === 'admin' ? normalizeAdminTab(segments[1] || 'orders') : '',
      queryString,
      params
    };
  }

  function hashFor(path) {
    if (!path || path === '/') return '#/';
    if (String(path).startsWith('#')) return String(path);
    return `#${String(path).startsWith('/') ? path : `/${path}`}`;
  }

  function go(path, replace = false) {
    const target = hashFor(path);
    if (window.location.hash === target) {
      window.dispatchEvent(new Event('hashchange'));
      return;
    }
    if (replace) {
      window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}${target}`);
      window.dispatchEvent(new Event('hashchange'));
      return;
    }
    window.location.hash = target;
  }

  function techRoutePath(params = {}) {
    const query = new URLSearchParams();
    if (params.claim) query.set('claim', params.claim);
    if (params.wx) query.set('wx', '1');
    const suffix = query.toString();
    return `/tech${suffix ? `?${suffix}` : ''}`;
  }

  function techLandingPath(params = {}) {
    return techRoutePath({ ...params, wx: '1' });
  }

  function techNext(params = {}) {
    return `${APP_BASE}/index.html#${techLandingPath(params)}`;
  }

  function wechatStartHref(params = {}) {
    return `api/index.php?route=wechat.start&next=${encodeURIComponent(techNext(params))}`;
  }

  async function refreshAuthState(options = {}) {
    if (typeof window.FDX_REFRESH_AUTH === 'function') {
      const state = await window.FDX_REFRESH_AUTH(options);
      if (state) return state;
    }
    if (typeof window.FDX_GET_AUTH === 'function') return window.FDX_GET_AUTH();
    return null;
  }

  function roleLabelFor(staff) {
    if (!staff) return '';
    if (staff.role_label) return staff.role_label;
    const labels = {
      admin: '管理员',
      intake: '录入',
      dispatcher: '领取机器',
      service: '取机',
      viewer: '查看',
      duty: '现场值班'
    };
    return labels[staff.role] || staff.role || '';
  }

  function stationsFor(staff) {
    const role = staff?.role || '';
    const canUseOnsite = ['admin', 'duty'].includes(role);
    return [
      { key: 'intake', station: '1/4', title: '接机位', desc: '录入新订单', metric: 'waiting_assignment', href: '#/intake', enabled: canUseOnsite || role === 'intake' },
      { key: 'dispatch', station: '6', title: '技术员取机位', desc: '技术员领取电脑及派单管理', metric: 'waiting_assignment', href: '#/dispatch', enabled: canUseOnsite || role === 'dispatcher' },
      { key: 'service', station: '5', title: '机主取机位', desc: '机主取机通知及管理', metric: 'ready_for_pickup', href: '#/service', enabled: canUseOnsite || role === 'service' }
    ];
  }

  function serviceTypes(value) {
    return parseServiceTypes(value);
  }

  function ownerLine(order) {
    return `${order.customer_name || ''} ${order.customer_phone_mask || ''}`.trim() || '未填机主';
  }

  function deviceLine(order) {
    return `${order.device_type || ''} ${order.device_model || ''}`.trim() || '未填设备';
  }

  function boolText(value) {
    if (value === null || value === undefined || value === '') return '未指定';
    return Number(value) === 1 ? '是' : '否';
  }

  function assignmentStatusLabel(status) {
    const labels = {
      active: '当前分配',
      transferred: '已转单',
      closed: '已关闭',
      cancelled: '已取消'
    };
    return labels[status] || '未知';
  }

  function repairResultLabel(result) {
    const labels = {
      fixed: '已修复',
      partially_fixed: '部分修复',
      unfixed: '未修复',
      needs_followup: '需后续处理'
    };
    return labels[result] || '未记录';
  }

  function smsStatusLabel(status) {
    const labels = {
      manual_sent: '已手动发送',
      sent: '已发送',
      pending: '待发送',
      failed: '发送失败',
      generated: '已生成'
    };
    return labels[status] || '未记录';
  }

  function makeSubmissionToken(prefix = 'fdx') {
    if (window.crypto?.randomUUID) return `${prefix}:${window.crypto.randomUUID()}`;
    return `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`;
  }

  function techRepairDraftKey(orderId) {
    return `fdx:tech-repair-draft:${orderId}`;
  }

  function readJsonStorage(key) {
    try {
      if (!window.localStorage) return null;
      const raw = window.localStorage.getItem(key);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
      return null;
    }
  }

  function writeJsonStorage(key, value) {
    try {
      if (!window.localStorage) return;
      window.localStorage.setItem(key, JSON.stringify(value));
    } catch {}
  }

  function removeStorage(key) {
    try {
      if (!window.localStorage) return;
      window.localStorage.removeItem(key);
    } catch {}
  }

  function serializeFormDraft(form) {
    const fields = {};
    form.querySelectorAll('input[name], textarea[name], select[name]').forEach((input) => {
      if (!input.name || input.disabled) return;
      if (input.type === 'hidden' && !['technician_signature', 'customer_signature'].includes(input.name)) return;
      if (input.type === 'radio') {
        if (input.checked) fields[input.name] = input.value;
        return;
      }
      if (input.type === 'checkbox') {
        if (!Array.isArray(fields[input.name])) fields[input.name] = [];
        if (input.checked) fields[input.name].push(input.value || true);
        return;
      }
      fields[input.name] = input.value ?? '';
    });
    return fields;
  }

  function applyFormDraft(form, fields) {
    if (!fields || typeof fields !== 'object') return false;
    let restored = false;
    form.querySelectorAll('input[name], textarea[name], select[name]').forEach((input) => {
      if (!input.name || !Object.prototype.hasOwnProperty.call(fields, input.name)) return;
      const value = fields[input.name];
      if (input.type === 'radio') {
        const checked = String(value ?? '') === input.value;
        input.checked = checked;
        restored = restored || checked;
        return;
      }
      if (input.type === 'checkbox') {
        const values = Array.isArray(value) ? value.map((item) => String(item)) : [String(value)];
        input.checked = values.includes(String(input.value || true));
        restored = restored || input.checked;
        return;
      }
      input.value = Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
      restored = restored || input.value !== '';
    });
    return restored;
  }

  function signatureReady(form, field, label) {
    const input = form.querySelector(`[name="${field}"]`);
    if (input?.value) return true;
    showGlobalMessage({ success: false, message: `请先完成${label}` });
    return false;
  }

  const Topbar = {
    props: ['label', 'home'],
    template: `
      <header class="topbar">
        <a :href="home || '#/'">飞大修</a>
        <span>{{ label }}</span>
      </header>
    `
  };

  const HomePage = {
    computed: {
      techEntryHref() {
        return wechatStartHref();
      }
    },
    template: `
      <main class="shell">
        <section class="hero">
          <p class="eyebrow">飞扬俱乐部</p>
          <h1>飞大修</h1>
          <div class="actions">
            <a class="button primary" :href="techEntryHref">技术员入口</a>
            <a class="button" href="#/query">机主查询</a>
            <a class="button" href="#/login">现场值班登录</a>
            <a class="button" href="#/admin-login">管理后台</a>
          </div>
        </section>
      </main>
    `
  };

  const CustomerQueryPage = {
    data() {
      return { orders: [], phoneMask: '', searched: false };
    },
    methods: {
      statusLabel,
      deviceLine,
      async submit(event) {
        const button = event.submitter || event.currentTarget.querySelector('[type="submit"]');
        const data = await runWithButton(button, () => api('orders.customer_lookup', formToPayload(event.currentTarget)), '查询中...');
        this.searched = true;
        if (!data.success) {
          this.orders = [];
          this.phoneMask = '';
          showGlobalMessage(data);
          return;
        }
        this.orders = data.data?.orders || [];
        this.phoneMask = data.data?.phone_mask || '';
        showGlobalMessage(data);
      }
    },
    template: `
      <main class="shell compact">
        <Topbar label="机主查询" home="#/"></Topbar>
        <section class="card">
          <h1>订单查询</h1>
          <form class="form" @submit.prevent="submit">
            <label>手机号<input name="phone" type="tel" inputmode="numeric" autocomplete="tel" required></label>
            <button class="button primary" type="submit">查询订单</button>
          </form>
          <section v-if="orders.length" class="tech-section">
            <div class="section-head"><h2>{{ phoneMask }} 的订单</h2><span class="muted">{{ orders.length }} 单</span></div>
            <div class="order-list">
              <article class="order-item" v-for="order in orders" :key="order.order_no + order.created_at">
                <div class="order-heading"><strong>{{ order.order_no }}</strong><span class="status-pill">{{ statusLabel(order.status) }}</span></div>
                <p>{{ order.activity_name || '大修活动' }} · {{ order.activity_date || '' }} {{ order.activity_location || '' }}</p>
                <p>{{ deviceLine(order) }}</p>
                <p>{{ order.problem_description || '' }}</p>
                <p>技术员：{{ order.technician_name || '未分配' }}</p>
                <p>更新时间：{{ order.updated_at || order.created_at }}</p>
              </article>
            </div>
          </section>
          <div v-else-if="searched" class="empty">未查询到订单</div>
        </section>
      </main>
    `
  };

  const StaffLoginPage = {
    data() {
      return {};
    },
    methods: {
      async submit(event) {
        const button = event.submitter || event.currentTarget.querySelector('[type="submit"]');
        const data = await runWithButton(button, () => api('staff.shift_login', formToPayload(event.currentTarget)), '登录中...');
        showGlobalMessage(data);
        if (data.success) {
          await refreshAuthState();
          setTimeout(() => go('/workbench'), 300);
        }
      }
    },
    template: `
      <main class="shell compact">
        <section class="card">
          <h1>现场值班</h1>
          <form class="form" @submit.prevent="submit">
            <label>值班码
              <input name="code" autocomplete="one-time-code" required>
            </label>
            <button class="button primary" type="submit">进入工作台</button>
          </form>
          <p><a href="#/admin-login">管理员登录</a></p>
        </section>
      </main>
    `
  };

  const AdminLoginPage = {
    data() {
      return {};
    },
    methods: {
      async submit(event) {
        const button = event.submitter || event.currentTarget.querySelector('[type="submit"]');
        const data = await runWithButton(button, () => api('staff.login', formToPayload(event.currentTarget)), '登录中...');
        showGlobalMessage(data);
        if (data.success) {
          await refreshAuthState();
          setTimeout(() => go('/admin'), 300);
        }
      }
    },
    template: `
      <main class="shell compact">
        <header class="topbar"><a href="#/">飞大修</a><span>管理员登录</span></header>
        <section class="card">
          <h1>管理员登录</h1>
          <form class="form" @submit.prevent="submit">
            <label>账号<input name="username" autocomplete="username" required></label>
            <label>密码<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="button primary" type="submit">登录</button>
          </form>
        </section>
      </main>
    `
  };

  const WorkbenchPage = {
    props: ['staff', 'roleLabel', 'canUseOnsite', 'stations'],
    data() {
      return {
        activity: null,
        stats: {
          waiting_assignment: 0,
          assigned: 0,
          repairing: 0,
          ready_for_pickup: 0,
          notified: 0,
          completed: 0,
          cancelled: 0
        }
      };
    },
    computed: {
      queueItems() {
        return [
          ['待分配', this.stats.waiting_assignment],
          ['待维修', this.stats.assigned],
          ['维修中', this.stats.repairing],
          ['已完成', this.stats.completed]
        ];
      },
      activityLine() {
        if (!this.activity) return '未设置当前活动';
        return [this.activity.name, this.activity.activity_date, this.activity.location].filter(Boolean).join(' · ');
      },
      taskRows() {
        return [
          ['待分配', this.stats.waiting_assignment, '#/dispatch'],
          ['待维修', this.stats.assigned, '#/dispatch'],
          ['维修中', this.stats.repairing, '#/service'],
          ['已完成', this.stats.completed, this.staff.role === 'admin' ? '#/admin/orders' : '#/workbench']
        ];
      }
    },
    methods: {
      async load() {
        const result = await api('dashboard.stats', null, 'GET');
        if (!result.success) {
          showGlobalMessage(result);
          return;
        }
        this.activity = result.data.activity;
        this.stats = result.data.stats || this.stats;
      },
      async logout(event) {
        const data = await runWithButton(event.currentTarget, () => api('staff.logout', {}), '退出中...');
        if (data.success) {
          await refreshAuthState();
          go('/login');
        }
        else showGlobalMessage(data);
      }
    },
    mounted() {
      this.load();
      this.timer = window.setInterval(() => !document.hidden && this.load(), 10000);
    },
    unmounted() {
      window.clearInterval(this.timer);
    },
    template: `
      <main class="ops-shell">
        <header class="ops-topbar">
          <a class="brand-mark" href="#/">飞大修</a>
          <div class="topbar-actions">
            <span>{{ staff.display_name }} · {{ roleLabel }}</span>
            <button class="button" type="button" @click="logout">退出</button>
          </div>
        </header>
        <section class="ops-hero">
          <div>
            <p class="eyebrow">工作台</p>
            <h1>现场流转</h1>
            <div class="ops-meta">{{ activityLine }}</div>
          </div>
          <div class="queue-snapshot">
            <div class="snapshot-item" v-for="item in queueItems" :key="item[0]">
              <span>{{ item[0] }}</span>
              <strong>{{ Number(item[1] || 0) }}</strong>
            </div>
          </div>
        </section>
        <section class="ops-layout">
          <section class="ops-panel">
            <div class="section-head">
              <h2>岗位流转</h2>
              <button class="button" type="button" @click="load">刷新</button>
            </div>
            <div class="flow-rail">
              <component
                :is="station.enabled ? 'a' : 'div'"
                v-for="station in stations"
                :key="station.key"
                class="flow-step"
                :class="{ disabled: !station.enabled }"
                :href="station.enabled ? station.href : null">
                <span class="step-no">{{ station.station }}</span>
                <span class="step-copy">
                  <strong>{{ station.title }}</strong>
                  <small>{{ station.desc }}</small>
                </span>
                <strong class="step-count">{{ Number(stats[station.metric] || 0) }}</strong>
                <span v-if="station.enabled" class="station-enter">进入岗位</span>
              </component>
            </div>
          </section>
          <aside class="ops-side">
            <section class="ops-panel compact-panel">
              <h2>当前队列</h2>
              <div class="task-list">
                <a class="task-row" v-for="row in taskRows" :href="row[2]" :key="row[0]">
                  <span>{{ row[0] }}</span>
                  <strong>{{ Number(row[1] || 0) }} 单</strong>
                </a>
              </div>
            </section>
            <section v-if="staff.role === 'admin'" class="ops-panel compact-panel">
              <h2>管理</h2>
              <a class="button primary full-button" href="#/admin/orders">进入管理后台</a>
            </section>
          </aside>
        </section>
      </main>
    `
  };

  const IntakePage = {
    data() {
      return { odd: '----', even: '----', position: '', isSubmitting: false, formVersion: 0, submissionToken: makeSubmissionToken('intake') };
    },
    methods: {
      async refreshNumbers() {
        const [odd, even] = await Promise.all([
          loadNextNumber('1').catch(() => ''),
          loadNextNumber('4').catch(() => '')
        ]);
        this.odd = odd || '----';
        this.even = even || '----';
        if (this.$refs.orderNumber) {
          this.$refs.orderNumber.value = this.position === '1' ? odd : (this.position === '4' ? even : '');
        }
      },
      async selectPosition() {
        if (this.$refs.orderNumber) {
          this.$refs.orderNumber.value = this.position ? await loadNextNumber(this.position).catch(() => '') : '';
        }
      },
      async submit(event, addNext = false) {
        if (this.isSubmitting) return;
        if (!event.currentTarget.reportValidity()) return;
        const payload = formToPayload(event.currentTarget);
        payload.intake_token = this.submissionToken;
        this.isSubmitting = true;
        try {
          const result = await api('orders.create', payload);
          showGlobalMessage(result);
          if (!result.success) return;
          const keep = payload.position || '';
          this.position = addNext ? keep : '';
          this.submissionToken = makeSubmissionToken('intake');
          this.formVersion += 1;
          await nextTick();
          await Promise.all([
            this.refreshNumbers(),
            this.$refs.currentOrders?.load?.()
          ]);
          window.scrollTo({ top: 0, behavior: 'smooth' });
          if (addNext) {
            this.$refs.form?.querySelector('[name="customer_name"]')?.focus();
          } else {
            if (this.$refs.orderNumber) this.$refs.orderNumber.value = '';
          }
        } finally {
          this.isSubmitting = false;
        }
      },
      async resetForm() {
        this.position = '';
        this.submissionToken = makeSubmissionToken('intake');
        this.formVersion += 1;
        await nextTick();
        if (this.$refs.orderNumber) this.$refs.orderNumber.value = '';
        await this.refreshNumbers();
      }
    },
    mounted() {
      this.refreshNumbers();
    },
    template: `
      <main class="shell">
        <Topbar label="订单录入" home="#/workbench"></Topbar>
        <section class="card">
          <h1>订单录入</h1>
          <div class="number-grid" aria-live="polite">
            <div class="number-panel number-panel-blue"><h2>1号位 (单号)</h2><p>负责录入：0001, 0003, 0005...</p><strong>{{ odd }}</strong></div>
            <div class="number-panel number-panel-green"><h2>4号位 (双号)</h2><p>负责录入：0002, 0004, 0006...</p><strong>{{ even }}</strong></div>
          </div>
          <form :key="formVersion" ref="form" class="form intake-form" @submit.prevent="submit($event, false)">
            <div class="form-strip">
              <label>录入位置
                <select name="position" v-model="position" @change="selectPosition" required>
                  <option value="">请选择录入位置</option>
                  <option value="1">1号位 (单号)</option>
                  <option value="4">4号位 (双号)</option>
                </select>
              </label>
              <label>维修编号<input ref="orderNumber" type="text" name="order_number" readonly></label>
            </div>
            <div class="form-grid two">
              <section class="form-section">
                <h2>机主个人基本信息</h2>
                <label>姓名<input type="text" name="customer_name" autocomplete="name" required></label>
                <div class="field-group"><span>性别</span><div class="choice-row"><label><input type="radio" name="gender" value="男"> 男</label><label><input type="radio" name="gender" value="女"> 女</label></div></div>
                <label>手机号<input type="tel" name="customer_phone" autocomplete="tel" inputmode="numeric" required></label>
                <label>备用联系人手机号<input type="tel" name="backup_phone" autocomplete="tel" inputmode="numeric"></label>
                <label>就读学院<input type="text" name="college"></label>
                <label>居住宿舍<input type="text" name="dormitory"></label>
                <label>学号<input type="text" name="student_id"></label>
                <label>QQ号码<input type="text" name="customer_qq" inputmode="numeric"></label>
              </section>
              <section class="form-section">
                <h2>待修电脑基本信息</h2>
                <label>设备类型<select name="device_type" required><option value="">请选择设备类型</option><option>台式机</option><option>笔记本</option><option>一体机</option><option>其他</option></select></label>
                <label>品牌与型号<input type="text" name="device_model"></label>
                <label>开机密码<input type="text" name="login_password" autocomplete="off"></label>
                <label>外带附件<textarea name="accessories" rows="2" maxlength="200" placeholder="如：电源线、鼠标、键盘等"></textarea></label>
                <label>电脑已经存在的外观及硬件损坏与缺陷<textarea name="existing_damage" rows="2" maxlength="200"></textarea></label>
                <div class="field-group required-confirm"><span>确认过保</span><label class="check-line"><input type="checkbox" name="confirmed_out_of_warranty" value="1" required> 已确认设备过保</label></div>
              </section>
            </div>
            <section class="form-section">
              <h2>所需服务</h2>
              <div class="checkbox-grid">
                <label><input type="checkbox" name="service_type[]" value="硬件初级检修"> 硬件初级检修</label>
                <label><input type="checkbox" name="service_type[]" value="拆机清洁"> 拆机清洁</label>
                <label><input type="checkbox" name="service_type[]" value="数据恢复"> 数据恢复</label>
                <label><input type="checkbox" name="service_type[]" value="软件、驱动安装"> 软件、驱动安装</label>
                <label><input type="checkbox" name="service_type[]" value="病毒清除、系统修复"> 病毒清除、系统修复</label>
                <label><input type="checkbox" name="service_type[]" value="系统优化"> 系统优化</label>
              </div>
              <div class="form-grid two">
                <div class="field-group"><span>若维修进行中有必要是否可以采用系统重装</span><div class="choice-row"><label><input type="radio" name="allow_system_reinstall" value="是"> 是</label><label><input type="radio" name="allow_system_reinstall" value="否"> 否</label></div></div>
                <div class="field-group"><span>在进行系统重装操作时是否可以清空硬盘</span><div class="choice-row"><label><input type="radio" name="allow_disk_format" value="是"> 是</label><label><input type="radio" name="allow_disk_format" value="否"> 否</label></div></div>
              </div>
              <label>故障描述 <span class="required">*</span><textarea name="problem_description" rows="3" required maxlength="500" placeholder="请详细描述设备故障现象..."></textarea></label>
              <label>需要进行备份的重要数据<textarea name="important_data" rows="2" maxlength="200"></textarea></label>
              <label>对于故障或维修要求的补充描述<textarea name="repair_notes" rows="2" maxlength="200"></textarea></label>
            </section>
            <label>备注<textarea name="notes" rows="2" maxlength="200" placeholder="其他需要说明的情况..."></textarea></label>
            <div class="form-actions">
              <button class="button" type="button" :disabled="isSubmitting" @click="resetForm">重置</button>
              <button ref="submitBtn" class="button primary" type="submit" :disabled="isSubmitting">{{ isSubmitting ? '提交中...' : '提交订单' }}</button>
              <button class="button success" type="button" :disabled="isSubmitting" @click="submit({ currentTarget: $refs.form, submitter: $event.currentTarget }, true)">{{ isSubmitting ? '提交中...' : '提交并添加下一个' }}</button>
            </div>
          </form>
        </section>
        <CurrentOrdersPanel ref="currentOrders"></CurrentOrdersPanel>
      </main>
    `
  };

  const QueueMixin = {
    methods: {
      serviceTypes,
      statusLabel,
      ownerLine,
      deviceLine,
      matches(order) {
        const q = (this.search || '').trim().toLowerCase();
        if (!q) return true;
        return [order.order_no, order.customer_name, order.customer_phone, order.customer_phone_mask, order.device_type, order.device_model, order.problem_description, order.technician_name].filter(Boolean).join(' ').toLowerCase().includes(q);
      }
    }
  };

  const CurrentOrdersPanel = {
    props: {
      title: { type: String, default: '当前大修全部订单' }
    },
    data() {
      return { orders: [], q: '', status: '', detail: null, loading: false };
    },
    computed: {
      filteredOrders() {
        const status = this.status || '';
        const q = (this.q || '').trim().toLowerCase();
        return this.orders.filter((order) => {
          if (status && order.status !== status) return false;
          if (!q) return true;
          return [
            order.order_no,
            order.customer_name,
            order.customer_phone,
            order.customer_phone_mask,
            order.backup_phone,
            order.customer_qq,
            order.college,
            order.dormitory,
            order.student_id,
            order.device_type,
            order.device_model,
            order.problem_description,
            order.repair_notes,
            order.technician_name
          ].filter(Boolean).join(' ').toLowerCase().includes(q);
        });
      },
      statusOptions() {
        return [
          ['waiting_assignment', '待技术员接机'],
          ['assigned', '已分配'],
          ['repairing', '维修中'],
          ['ready_for_pickup', '待通知'],
          ['notified', '待取机'],
          ['completed', '已完成'],
          ['cancelled', '已取消']
        ];
      }
    },
    methods: {
      statusLabel,
      serviceTypes,
      ownerLine,
      deviceLine,
      boolText,
      assignmentStatusLabel,
      repairResultLabel,
      smsStatusLabel,
      ownerFullLine(order) {
        return `${order.customer_name || ''} ${order.customer_phone || order.customer_phone_mask || ''}`.trim() || '未填机主';
      },
      activityLine(order) {
        return [order.activity_name, order.activity_date, order.activity_location].filter(Boolean).join(' · ') || '未填';
      },
      detailRows(order) {
        return [
          ['编号', order.order_no],
          ['状态', this.statusLabel(order.status)],
          ['活动', this.activityLine(order)],
          ['录入位置', order.station_no ? `${order.station_no}号位` : '未填'],
          ['机主', this.ownerFullLine(order)],
          ['备用电话', order.backup_phone || '未填'],
          ['QQ', order.customer_qq || '未填'],
          ['性别', order.gender || '未填'],
          ['学院', order.college || '未填'],
          ['宿舍', order.dormitory || '未填'],
          ['学号', order.student_id || '未填'],
          ['设备', this.deviceLine(order)],
          ['开机密码', order.boot_password || '未填'],
          ['外带附件', order.accessories || '未填'],
          ['外观及硬件损坏', order.existing_damage || '未填'],
          ['服务', this.serviceTypes(order.service_types).join('、') || '未指定'],
          ['确认过保', Number(order.confirmed_out_of_warranty) === 1 ? '已确认' : '未确认'],
          ['允许重装系统', this.boolText(order.allow_reinstall)],
          ['允许清空硬盘', this.boolText(order.allow_format)],
          ['重要数据', order.important_data || '未填'],
          ['故障描述', order.problem_description || '未填'],
          ['维修要求', order.repair_notes || '未填'],
          ['备注', order.notes || '未填'],
          ['创建时间', order.created_at || ''],
          ['更新时间', order.updated_at || ''],
          ['完成时间', order.completed_at || '未完成'],
          ['取消时间', order.cancelled_at || '未取消']
        ];
      },
      async load() {
        if (this.loading) return;
        this.loading = true;
        try {
          const data = await api('orders.list&limit=500', null, 'GET');
          if (data.success) this.orders = data.data.orders || [];
          else showGlobalMessage(data);
        } finally {
          this.loading = false;
        }
      },
      async openDetail(order) {
        const data = await api(`orders.detail&id=${Number(order.id)}`, null, 'GET');
        if (data.success) this.detail = data.data;
        else showGlobalMessage(data);
      }
    },
    mounted() {
      this.load();
      this.timer = window.setInterval(() => !document.hidden && this.load(), 6000);
    },
    unmounted() {
      window.clearInterval(this.timer);
    },
    template: `
      <section class="ops-panel current-orders-panel">
        <div class="section-head">
          <div><p class="eyebrow">当前大修</p><h2>{{ title }}</h2></div>
          <span class="muted">{{ filteredOrders.length }}/{{ orders.length }} 单</span>
        </div>
        <form class="toolbar current-orders-toolbar" @submit.prevent="load">
          <input v-model="q" type="search" placeholder="搜索编号、姓名、手机号、设备、故障、技术员">
          <select v-model="status">
            <option value="">全部状态</option>
            <option v-for="item in statusOptions" :key="item[0]" :value="item[0]">{{ item[1] }}</option>
          </select>
          <button class="button" type="submit" :disabled="loading">{{ loading ? '刷新中...' : '刷新' }}</button>
        </form>
        <div class="admin-table current-orders-table">
          <div class="table-head"><span>编号</span><span>状态</span><span>机主</span><span>设备</span><span>技术员</span><span>操作</span></div>
          <article v-for="order in filteredOrders" :key="order.id" class="table-row">
            <strong>{{ order.order_no }}</strong>
            <span class="status-pill">{{ statusLabel(order.status) }}</span>
            <span>{{ ownerLine(order) }}</span>
            <span>{{ deviceLine(order) }}</span>
            <span>{{ order.technician_name || '未分配' }}</span>
            <span class="table-actions"><button class="button" type="button" @click="openDetail(order)">详情</button></span>
          </article>
          <div v-if="!filteredOrders.length" class="empty">暂无订单</div>
        </div>
        <div v-if="detail" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="detail = null"></div>
          <section class="modal-panel modal-panel-wide">
            <div class="section-head"><h1>订单 {{ detail.order.order_no }}</h1><button class="button" type="button" @click="detail = null">关闭</button></div>
            <div class="detail-body">
              <section class="detail-block"><h2>订单信息</h2><div class="detail-grid"><div class="field-row" v-for="row in detailRows(detail.order)" :key="row[0]"><span>{{ row[0] }}</span><strong>{{ row[1] }}</strong></div></div></section>
              <section class="detail-block"><h2>技术员分配</h2><div class="record-list"><article v-for="row in detail.assignments" :key="row.id" class="record-item"><strong>{{ row.technician_name }}</strong><span>{{ assignmentStatusLabel(row.status) }} · {{ row.assigned_at }}</span><p>{{ row.notes || '' }}</p></article><div v-if="!detail.assignments.length" class="empty">暂无记录</div></div></section>
              <section class="detail-block"><h2>维修记录</h2><div class="record-list"><article v-for="row in detail.repair_records" :key="row.id" class="record-item"><strong>{{ row.technician_name }}</strong><span>{{ repairResultLabel(row.result) }} · {{ row.submitted_at }}</span><p>诊断：{{ row.diagnosis }}</p><p>方案：{{ row.solution }}</p><p>{{ row.notes || '' }}</p><img v-if="row.technician_signature" class="signature-preview" :src="row.technician_signature" alt="技术员签字"></article><div v-if="!detail.repair_records.length" class="empty">暂无记录</div></div></section>
              <section class="detail-block"><h2>取机记录</h2><div class="record-list"><article v-for="row in detail.pickup_records" :key="row.id" class="record-item"><strong>{{ row.pickup_time }}</strong><span>{{ row.handler_name || '系统/旧系统' }}</span><p>{{ row.notes || '' }}</p><img v-if="row.customer_signature" class="signature-preview" :src="row.customer_signature" alt="机主签字"></article><div v-if="!detail.pickup_records.length" class="empty">暂无记录</div></div></section>
              <section class="detail-block"><h2>短信记录</h2><div class="record-list"><article v-for="row in detail.sms_logs" :key="row.id" class="record-item"><strong>{{ smsStatusLabel(row.status) }}</strong><span>{{ row.sent_at || row.created_at }}</span><p>{{ row.phone_mask }} · {{ row.content }}</p></article><div v-if="!detail.sms_logs.length" class="empty">暂无记录</div></div></section>
            </div>
          </section>
        </div>
      </section>
    `
  };

  const DispatchPage = {
    mixins: [QueueMixin],
    data() {
      return { orders: [], technicians: [], search: '', activeStatus: 'waiting_assignment', claim: null, assignOrder: null, techSearch: '', assigningTechId: null };
    },
    computed: {
      statusTabs() {
        return [
          { key: 'waiting_assignment', label: '待分配' },
          { key: 'assigned', label: '待维修' },
          { key: 'repairing', label: '维修中' }
        ];
      },
      statusCounts() {
        return this.orders.reduce((counts, order) => {
          counts[order.status] = (counts[order.status] || 0) + 1;
          return counts;
        }, {});
      },
      activeTab() {
        return this.statusTabs.find((tab) => tab.key === this.activeStatus) || this.statusTabs[0];
      },
      activeStatusTotal() {
        return Number(this.statusCounts[this.activeStatus] || 0);
      },
      activeStatusOrders() {
        return this.orders.filter((order) => order.status === this.activeStatus);
      },
      filtered() {
        return this.activeStatusOrders.filter(this.matches);
      },
      activeTechnicianCount() {
        return this.technicians.filter((tech) => tech.status === 'active').length;
      },
      filteredTechnicians() {
        const q = (this.techSearch || '').trim().toLowerCase();
        const list = this.technicians.filter((tech) => tech.status === 'active');
        if (!q) return list;
        return list.filter((tech) => [tech.display_name, tech.phone, tech.campus].filter(Boolean).join(' ').toLowerCase().includes(q));
      }
    },
    methods: {
      async load() {
        const statuses = this.statusTabs.map((tab) => tab.key);
        const [techResult, ...orderResults] = await Promise.all([
          api('technicians.list', null, 'GET'),
          ...statuses.map((status) => api(`orders.list&status=${encodeURIComponent(status)}&limit=500`, null, 'GET'))
        ]);
        const failed = orderResults.find((item) => !item.success);
        if (failed) {
          showGlobalMessage(failed);
          return;
        }
        this.technicians = techResult.success ? (techResult.data.technicians || []) : [];
        this.orders = orderResults.flatMap((item) => item.data.orders || []);
      },
      async openAssign(order) {
        this.assignOrder = order;
        this.techSearch = '';
        await nextTick();
        this.$refs.techSearchInput?.focus();
      },
      closeAssign() {
        this.assignOrder = null;
        this.techSearch = '';
        this.assigningTechId = null;
      },
      isCurrentTech(tech) {
        return this.assignOrder?.technician_id && String(this.assignOrder.technician_id) === String(tech.id);
      },
      async assignToTech(tech) {
        if (!this.assignOrder || this.assigningTechId) return;
        this.assigningTechId = tech.id;
        try {
          const data = await api('orders.assign', { order_id: this.assignOrder.id, technician_id: tech.id });
          showGlobalMessage(data);
          if (data.success) {
            this.closeAssign();
            this.load();
          }
        } finally {
          this.assigningTechId = null;
        }
      },
      async qr(order, event) {
        const data = await runWithButton(event.currentTarget, () => api('orders.claim_link', { order_id: order.id }), '生成中...');
        if (!data.success) {
          showGlobalMessage(data);
          return;
        }
        this.claim = data.data;
        await nextTick();
        const target = this.$refs.claimQr;
        target.innerHTML = '';
        new QRCode(target, { text: data.data.url, width: 220, height: 220, colorDark: '#172033', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        this.load();
      }
    },
    mounted() {
      this.load();
      this.timer = window.setInterval(() => !document.hidden && this.load(), 6000);
    },
    unmounted() {
      window.clearInterval(this.timer);
    },
    template: `
      <main class="ops-shell">
        <header class="ops-topbar"><a class="brand-mark" href="#/workbench">飞大修</a><span>6 号位领取机器</span></header>
        <section class="ops-panel">
          <div class="queue-head"><div><p class="eyebrow">6 号位</p><h1>{{ activeTab.label }}</h1></div><div class="queue-counter"><strong>{{ filtered.length }}</strong><span>{{ filtered.length }}/{{ activeStatusTotal }} 单</span></div></div>
          <nav class="status-tabs" aria-label="订单状态">
            <button v-for="tab in statusTabs" :key="tab.key" class="status-tab" :class="{active: activeStatus === tab.key}" type="button" @click="activeStatus = tab.key">
              <span>{{ tab.label }}</span><strong>{{ Number(statusCounts[tab.key] || 0) }}</strong>
            </button>
          </nav>
          <div class="queue-toolbar"><input v-model="search" type="search" placeholder="搜索编号、姓名、设备、故障"><button class="button" type="button" @click="load">刷新</button></div>
          <div class="order-board">
            <article class="queue-card" v-for="order in filtered" :key="order.id">
              <div class="queue-card-main">
                <div class="order-heading"><strong>{{ order.order_no }}</strong><span class="status-pill">{{ statusLabel(order.status) }}</span></div>
                <div class="queue-meta"><span>{{ ownerLine(order) }}</span><span>{{ deviceLine(order) }}</span><span>{{ order.created_at }}</span></div>
                <div class="tag-row"><span class="tag" v-for="tag in serviceTypes(order.service_types)" :key="tag">{{ tag }}</span><span v-if="!serviceTypes(order.service_types).length" class="tag muted-tag">未选服务</span></div>
                <p class="queue-problem">{{ order.problem_description || '未填故障描述' }}</p>
              </div>
              <div class="queue-actions">
                <div v-if="order.technician_name" class="assigned-tech">当前：{{ order.technician_name }}</div>
                <button class="button primary" type="button" @click="openAssign(order)">{{ order.status === 'waiting_assignment' ? '分配技术员' : '转单' }}</button>
                <button v-if="order.status === 'waiting_assignment'" class="button" type="button" @click="qr(order, $event)">二维码</button>
              </div>
            </article>
            <div v-if="!filtered.length" class="empty">暂无{{ activeTab.label }}订单</div>
          </div>
        </section>
        <CurrentOrdersPanel></CurrentOrdersPanel>
        <div v-if="claim" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="claim = null"></div>
          <section class="modal-panel"><div class="section-head"><h1>扫码领取</h1><button class="button" type="button" @click="claim = null">关闭</button></div><div ref="claimQr" class="qr-box"></div><input :value="claim.url" readonly></section>
        </div>
        <div v-if="assignOrder" class="modal" aria-hidden="false" @keydown.esc="closeAssign">
          <div class="modal-backdrop" @click="closeAssign"></div>
          <section class="modal-panel modal-panel-tech">
            <div class="section-head">
              <div><p class="eyebrow">{{ assignOrder.order_no }}</p><h1>{{ assignOrder.status === 'waiting_assignment' ? '分配技术员' : '转单给技术员' }}</h1></div>
              <button class="button" type="button" @click="closeAssign">关闭</button>
            </div>
            <div class="tech-picker-summary">
              <strong>{{ deviceLine(assignOrder) }}</strong>
              <span>{{ ownerLine(assignOrder) }}</span>
              <span v-if="assignOrder.technician_name">当前：{{ assignOrder.technician_name }}</span>
            </div>
            <input ref="techSearchInput" v-model="techSearch" class="tech-picker-search" type="search" placeholder="搜索姓名、手机号、校区">
            <div class="tech-picker-count">匹配 {{ filteredTechnicians.length }} / {{ activeTechnicianCount }} 位技术员</div>
            <div class="tech-picker-list">
              <button v-for="tech in filteredTechnicians" :key="tech.id" class="tech-picker-item" :class="{active: isCurrentTech(tech)}" type="button" :disabled="!!assigningTechId" @click="assignToTech(tech)">
                <span class="tech-picker-main"><strong>{{ tech.display_name }}</strong><span>{{ tech.phone || '未填手机号' }}</span></span>
                <span class="tech-picker-meta"><span>{{ tech.campus || '未填校区' }}</span><span>{{ Number(tech.active_orders || 0) }} 单进行中</span><span v-if="isCurrentTech(tech)">当前技术员</span></span>
                <span class="tech-picker-action">{{ assigningTechId === tech.id ? (assignOrder.status === 'waiting_assignment' ? '分配中' : '转单中') : '选择' }}</span>
              </button>
              <div v-if="!filteredTechnicians.length" class="empty">没有匹配的技术员</div>
            </div>
          </section>
        </div>
      </main>
    `
  };

  const ServicePage = {
    mixins: [QueueMixin],
    data() {
      return { orders: [], search: '', activeStatus: 'repairing', completeOrder: null, pickupOrder: null, sms: null };
    },
    computed: {
      statusTabs() {
        return [
          { key: 'repairing', label: '维修中' },
          { key: 'ready_for_pickup', label: '待通知' },
          { key: 'notified', label: '待取机' }
        ];
      },
      statusCounts() {
        return this.orders.reduce((counts, order) => {
          counts[order.status] = (counts[order.status] || 0) + 1;
          return counts;
        }, {});
      },
      activeTab() {
        return this.statusTabs.find((tab) => tab.key === this.activeStatus) || this.statusTabs[0];
      },
      activeStatusTotal() {
        return Number(this.statusCounts[this.activeStatus] || 0);
      },
      activeStatusOrders() {
        return this.orders.filter((order) => order.status === this.activeStatus);
      },
      filtered() {
        return this.activeStatusOrders.filter(this.matches);
      }
    },
    methods: {
      async load() {
        const statuses = this.statusTabs.map((tab) => tab.key);
        const results = await Promise.all(statuses.map((status) => api(`orders.list&status=${encodeURIComponent(status)}&limit=500`, null, 'GET')));
        const failed = results.find((item) => !item.success);
        if (failed) {
          showGlobalMessage(failed);
          return;
        }
        this.orders = results.flatMap((item) => item.data.orders || []);
      },
      openComplete(order) {
        this.completeOrder = order;
        nextTick(() => bindSignaturePads(this.$refs.completeModal));
      },
      openPickup(order) {
        this.pickupOrder = order;
        nextTick(() => bindSignaturePads(this.$refs.pickupModal));
      },
      async submitComplete(event) {
        if (!signatureReady(event.currentTarget, 'technician_signature', '技术员签字')) return;
        const data = await runWithButton(event.submitter, () => api('orders.staff_complete_repair', formToPayload(event.currentTarget)), '登记中...');
        showGlobalMessage(data);
        if (data.success) {
          this.completeOrder = null;
          this.load();
        }
      },
      async submitPickup(event) {
        if (!signatureReady(event.currentTarget, 'customer_signature', '机主签字')) return;
        const data = await runWithButton(event.submitter, () => api('orders.pickup', formToPayload(event.currentTarget)), '确认中...');
        showGlobalMessage(data);
        if (data.success) {
          this.pickupOrder = null;
          this.load();
        }
      },
      async showSms(order, event) {
        const data = await runWithButton(event.currentTarget, () => api('orders.sms_notice', { order_id: order.id }), '生成中...');
        if (!data.success) {
          showGlobalMessage(data);
          return;
        }
        this.sms = data.data;
        await nextTick();
        const target = this.$refs.smsQr;
        target.innerHTML = '';
        new QRCode(target, { text: data.data.url, width: 220, height: 220, colorDark: '#172033', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
      },
      async confirmSms(event) {
        if (!this.sms?.order_id) return;
        const data = await runWithButton(event.currentTarget, () => api('orders.sms_confirm', { order_id: this.sms.order_id }), '确认中...');
        showGlobalMessage(data);
        if (data.success) {
          this.sms = null;
          this.activeStatus = 'notified';
          this.load();
        }
      }
    },
    mounted() {
      this.load();
      this.timer = window.setInterval(() => !document.hidden && this.load(), 6000);
    },
    unmounted() {
      window.clearInterval(this.timer);
    },
    template: `
      <main class="ops-shell">
        <header class="ops-topbar"><a class="brand-mark" href="#/workbench">飞大修</a><span>5 号位送还与取机</span></header>
        <section class="ops-panel">
          <div class="queue-head"><div><p class="eyebrow">5 号位</p><h1>{{ activeTab.label }}</h1></div><div class="queue-counter"><strong>{{ filtered.length }}</strong><span>{{ filtered.length }}/{{ activeStatusTotal }} 单</span></div></div>
          <nav class="status-tabs" aria-label="订单状态">
            <button v-for="tab in statusTabs" :key="tab.key" class="status-tab" :class="{active: activeStatus === tab.key}" type="button" @click="activeStatus = tab.key">
              <span>{{ tab.label }}</span><strong>{{ Number(statusCounts[tab.key] || 0) }}</strong>
            </button>
          </nav>
          <div class="queue-toolbar"><input v-model="search" type="search" placeholder="搜索编号、姓名、设备、技术员"><button class="button" type="button" @click="load">刷新</button></div>
          <div class="order-board">
            <article class="queue-card" v-for="order in filtered" :key="order.id">
              <div class="queue-card-main">
                <div class="order-heading"><strong>{{ order.order_no }}</strong><span class="status-pill">{{ statusLabel(order.status) }}</span></div>
                <div class="queue-meta"><span>{{ ownerLine(order) }}</span><span>{{ deviceLine(order) }}</span><span>{{ order.created_at }}</span></div>
                <div class="tag-row"><span class="tag" v-for="tag in serviceTypes(order.service_types)" :key="tag">{{ tag }}</span><span v-if="!serviceTypes(order.service_types).length" class="tag muted-tag">未选服务</span></div>
                <p class="queue-problem">{{ order.problem_description || '未填故障描述' }}</p>
              </div>
              <div class="queue-actions">
                <div class="assigned-tech">技术员：{{ order.technician_name || '未分配' }}</div>
                <template v-if="order.status === 'ready_for_pickup'">
                  <button class="button" type="button" @click="showSms(order, $event)">短信通知</button>
                  <button class="button primary" type="button" @click="openPickup(order)">确认取机</button>
                </template>
                <button v-else-if="order.status === 'notified'" class="button primary" type="button" @click="openPickup(order)">确认取机</button>
                <button v-else class="button primary" type="button" @click="openComplete(order)">登记修完</button>
              </div>
            </article>
            <div v-if="!filtered.length" class="empty">暂无{{ activeTab.label }}订单</div>
          </div>
        </section>
        <CurrentOrdersPanel></CurrentOrdersPanel>
        <div v-if="completeOrder" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="completeOrder = null"></div>
          <section ref="completeModal" class="modal-panel modal-panel-wide">
            <div class="section-head"><h1>登记修完</h1><button class="button" type="button" @click="completeOrder = null">关闭</button></div>
            <form class="form" @submit.prevent="submitComplete">
              <input type="hidden" name="order_id" :value="completeOrder.id">
              <div class="form-grid two"><label>故障诊断<textarea name="diagnosis" rows="3" required></textarea></label><label>解决方案<textarea name="solution" rows="3" required></textarea></label></div>
              <label>维修结果<select name="result"><option value="fixed">已修复</option><option value="partially_fixed">部分修复</option><option value="unfixed">未修复</option><option value="needs_followup">需后续处理</option></select></label>
              <label>备注<textarea name="notes" rows="2"></textarea></label>
              <div class="signature-pad" data-signature-pad><div class="signature-head"><strong>技术员签字</strong><button class="button" type="button" data-signature-clear>清除</button></div><canvas></canvas><input type="hidden" name="technician_signature"></div>
              <div class="form-actions"><button class="button primary" type="submit">确认修完</button></div>
            </form>
          </section>
        </div>
        <div v-if="pickupOrder" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="pickupOrder = null"></div>
          <section ref="pickupModal" class="modal-panel modal-panel-wide">
            <div class="section-head"><h1>确认取机</h1><button class="button" type="button" @click="pickupOrder = null">关闭</button></div>
            <form class="form" @submit.prevent="submitPickup">
              <input type="hidden" name="order_id" :value="pickupOrder.id">
              <label>备注<textarea name="notes" rows="2"></textarea></label>
              <div class="signature-pad" data-signature-pad><div class="signature-head"><strong>机主取机签字</strong><button class="button" type="button" data-signature-clear>清除</button></div><canvas></canvas><input type="hidden" name="customer_signature"></div>
              <div class="form-actions"><button class="button primary" type="submit">完成取机</button></div>
            </form>
          </section>
        </div>
        <div v-if="sms" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="sms = null"></div>
          <section class="modal-panel"><div class="section-head"><h1>短信通知</h1><button class="button" type="button" @click="sms = null">关闭</button></div><div ref="smsQr" class="qr-box"></div><p class="sms-content">{{ sms.content }}</p><input :value="sms.url" readonly><div class="form-actions"><button class="button primary" type="button" @click="confirmSms($event)">确认已发送</button></div></section>
        </div>
      </main>
    `
  };

  const TechPage = {
    props: ['technician', 'wechat', 'error', 'claim', 'techNext'],
    data() {
      return {
        orders: [],
        detailOrder: null,
        reloginPending: false,
        draftCleanup: null,
        restoredDraftOrderId: null,
        keepAliveBusy: false,
        lastKeepAliveAt: 0
      };
    },
    computed: {
      activeOrders() {
        return this.orders.filter((order) => ['assigned', 'repairing'].includes(order.status));
      },
      historyOrders() {
        return this.orders.filter((order) => !['assigned', 'repairing'].includes(order.status));
      }
    },
    methods: {
      serviceTypes,
      statusLabel,
      ownerLine,
      deviceLine,
      techOwnerLine(order) {
        return `${order.customer_name || ''} ${order.customer_phone || order.customer_phone_mask || ''}`.trim() || '未填机主';
      },
      boolText(value) {
        if (value === null || value === undefined || value === '') return '未指定';
        return Number(value) === 1 ? '是' : '否';
      },
      openOrder(order) {
        this.detailOrder = order;
        nextTick(() => {
          bindSignaturePads(this.$refs.techRepairModal);
          this.setupRepairDraft();
        });
      },
      closeOrder() {
        this.teardownDraftAutosave();
        this.detailOrder = null;
      },
      syncDetailOrder() {
        if (!this.detailOrder) return;
        const fresh = this.orders.find((order) => String(order.id) === String(this.detailOrder.id));
        this.detailOrder = fresh || null;
      },
      currentRepairForm() {
        return this.$refs.techRepairModal?.querySelector?.('.repair-form') || null;
      },
      saveRepairDraft(form = this.currentRepairForm(), orderId = this.detailOrder?.id) {
        if (!form || !orderId) return false;
        writeJsonStorage(techRepairDraftKey(orderId), {
          order_id: Number(orderId),
          saved_at: Date.now(),
          fields: serializeFormDraft(form)
        });
        return true;
      },
      clearRepairDraft(orderId = this.detailOrder?.id) {
        if (!orderId) return;
        removeStorage(techRepairDraftKey(orderId));
        if (Number(this.restoredDraftOrderId) === Number(orderId)) {
          this.restoredDraftOrderId = null;
        }
      },
      restoreRepairDraft(form = this.currentRepairForm(), orderId = this.detailOrder?.id) {
        if (!form || !orderId) return false;
        const draft = readJsonStorage(techRepairDraftKey(orderId));
        if (!draft?.fields) return false;
        const restored = applyFormDraft(form, draft.fields);
        if (restored) bindSignaturePads(this.$refs.techRepairModal);
        if (restored && Number(this.restoredDraftOrderId) !== Number(orderId)) {
          this.restoredDraftOrderId = Number(orderId);
          showGlobalMessage({ success: true, message: '已恢复未提交的维修草稿' });
        }
        return restored;
      },
      teardownDraftAutosave() {
        if (typeof this.draftCleanup === 'function') this.draftCleanup();
        this.draftCleanup = null;
      },
      setupRepairDraft() {
        const form = this.currentRepairForm();
        const orderId = this.detailOrder?.id;
        this.teardownDraftAutosave();
        if (!form || !orderId) return;
        this.restoreRepairDraft(form, orderId);
        const persist = () => this.saveRepairDraft(form, orderId);
        form.addEventListener('input', persist);
        form.addEventListener('change', persist);
        this.draftCleanup = () => {
          form.removeEventListener('input', persist);
          form.removeEventListener('change', persist);
        };
      },
      async keepAlive(force = false) {
        if (!this.technician || this.reloginPending || this.keepAliveBusy) return this.technician;
        const now = Date.now();
        if (!force && this.lastKeepAliveAt && now - this.lastKeepAliveAt < 45000) {
          return this.technician;
        }

        this.keepAliveBusy = true;
        try {
          const auth = await refreshAuthState({ guard: false, silent: true });
          if (auth?.technician) {
            this.lastKeepAliveAt = Date.now();
          }
          return auth;
        } finally {
          this.keepAliveBusy = false;
        }
      },
      handleVisibilityChange() {
        if (!document.hidden) this.keepAlive(true);
      },
      handleWindowFocus() {
        this.keepAlive(true);
      },
      async runTechnicianRequest(task, options = {}) {
        if (this.reloginPending) {
          return { success: false, status: 401, message: '登录状态恢复中，请稍候', authHandled: true };
        }

        let data = await task();
        if (data.status !== 401) return data;

        if (options.form) this.saveRepairDraft(options.form, options.orderId);

        const auth = await refreshAuthState({ guard: false, silent: true });
        if (auth?.technician) {
          data = await task();
          if (data.status !== 401) return data;
        }

        this.reloginPending = true;
        this.closeOrder();
        window.clearInterval(this.timer);

        const message = options.message || (options.form
          ? '登录状态已失效，已为你保留维修草稿，请重新进入后继续提交'
          : '登录状态已失效，请重新进入后继续操作');

        showGlobalMessage({ success: false, message });

        if (auth?.wechat) {
          go(techLandingPath(this.claim ? { claim: this.claim } : {}), true);
        } else {
          window.setTimeout(() => {
            window.location.href = wechatStartHref(this.claim ? { claim: this.claim } : {});
          }, 500);
        }

        return { success: false, status: 401, message, authHandled: true };
      },
      async activate() {
        if (!this.technician || this.timer || this.reloginPending) return;
        await this.claimIfNeeded();
        if (!this.technician || this.reloginPending) return;
        await this.keepAlive(true);
        if (!this.technician || this.reloginPending) return;
        await this.load();
        if (!this.technician || this.reloginPending) return;
        if (!this.timer) {
          this.timer = window.setInterval(() => !document.hidden && !this.detailOrder && this.load(), 6000);
        }
      },
      async bindPhone(event) {
        const data = await runWithButton(event.submitter, () => api('technician.bind_phone', formToPayload(event.currentTarget)), '验证中...');
        showGlobalMessage(data);
        if (data.success) {
          await refreshAuthState();
          setTimeout(() => go(techLandingPath(this.claim ? { claim: this.claim } : {}), true), 500);
        }
      },
      async bindToken(event) {
        const data = await runWithButton(event.submitter, () => api('technician.bind', formToPayload(event.currentTarget)), '绑定中...');
        showGlobalMessage(data);
        if (data.success) {
          await refreshAuthState();
          setTimeout(() => go(techLandingPath(this.claim ? { claim: this.claim } : {}), true), 500);
        }
      },
      async claimIfNeeded() {
        if (!this.claim) return;
        const data = await this.runTechnicianRequest(() => api('orders.claim', { token: this.claim }), {
          message: '登录状态已失效，请重新进入后领取工单'
        });
        if (data.authHandled) return;
        if (!data.authHandled) showGlobalMessage(data);
        if (data.success) await refreshAuthState();
        go(techLandingPath(), true);
      },
      async load(force = false) {
        if (!force && (this.detailOrder || document.activeElement?.closest?.('.repair-form'))) return;
        const data = await this.runTechnicianRequest(() => api('technician.orders', null, 'GET'), {
          message: '登录状态已失效，请重新进入技术员入口'
        });
        if (!data.success) {
          if (!data.authHandled) showGlobalMessage(data);
          return;
        }
        this.orders = data.data.orders || [];
        this.syncDetailOrder();
        await nextTick();
        bindSignaturePads(this.$refs.techRepairModal);
      },
      async start(order, event) {
        const data = await runWithButton(event.currentTarget, () => this.runTechnicianRequest(
          () => api('orders.start_repair', { order_id: order.id }),
          { message: '登录状态已失效，请重新进入后再开始维修' }
        ), '开始中...');
        if (!data.authHandled) showGlobalMessage(data);
        if (data.success) {
          await this.load(true);
          const fresh = this.orders.find((item) => String(item.id) === String(order.id));
          this.detailOrder = fresh || { ...order, status: 'repairing' };
          await nextTick();
          bindSignaturePads(this.$refs.techRepairModal);
          this.setupRepairDraft();
        }
      },
      async submitRepair(event) {
        const form = event.currentTarget;
        if (!signatureReady(form, 'technician_signature', '技术员签字')) return;
        this.saveRepairDraft(form);
        await this.keepAlive(true);
        const data = await runWithButton(event.submitter, () => this.runTechnicianRequest(
          () => api('orders.submit_repair', formToPayload(form)),
          {
            form,
            orderId: this.detailOrder?.id,
            message: '登录状态已失效，已为你保留维修草稿，请重新进入后继续提交'
          }
        ), '提交中...');
        if (!data.authHandled) showGlobalMessage(data);
        if (data.success) {
          this.clearRepairDraft(this.detailOrder?.id);
          this.closeOrder();
          await this.load();
        }
      }
    },
    async mounted() {
      document.addEventListener('visibilitychange', this.handleVisibilityChange);
      window.addEventListener('focus', this.handleWindowFocus);
      await this.activate();
    },
    watch: {
      technician(value) {
        this.reloginPending = false;
        if (!value) {
          this.closeOrder();
          return;
        }
        this.activate();
      }
    },
    unmounted() {
      document.removeEventListener('visibilitychange', this.handleVisibilityChange);
      window.removeEventListener('focus', this.handleWindowFocus);
      window.clearInterval(this.timer);
      this.teardownDraftAutosave();
    },
    template: `
      <main class="shell compact">
        <Topbar label="技术员工作台" home="#/"></Topbar>
        <div v-if="error" class="notice danger">微信授权状态异常，请重新从服务号进入。</div>
        <section v-if="!wechat" class="card"><h1>微信登录失败</h1><a class="button primary" :href="techNext">重新进入</a></section>
        <section v-else-if="!technician" class="card">
          <h1>验证手机号并绑定</h1>
          <form class="form" @submit.prevent="bindPhone"><label>手机号<input name="phone" inputmode="numeric" autocomplete="tel" required></label><label>姓名，手机号重复时必填<input name="display_name" autocomplete="name"></label><button class="button primary" type="submit">验证并绑定</button></form>
          <details class="fallback"><summary>使用管理员绑定码</summary><form class="form" @submit.prevent="bindToken"><label>绑定码<input name="token" autocomplete="one-time-code" required></label><button class="button" type="submit">使用绑定码</button></form></details>
        </section>
        <section v-else class="card">
          <h1>{{ technician.display_name }} 的维修单</h1>
          <section class="tech-section">
            <div class="section-head"><h2>当前维修单</h2><span class="muted">{{ activeOrders.length }} 单</span></div>
            <div class="order-list">
              <button class="order-item tech-order-card" v-for="order in activeOrders" :key="order.id" type="button" @click="openOrder(order)">
                <span class="order-heading"><strong>{{ order.order_no }}</strong><span class="status-pill">{{ statusLabel(order.status) }}</span></span>
                <span>{{ order.activity_name || '' }}</span>
                <span>{{ deviceLine(order) }}</span>
                <span>{{ techOwnerLine(order) }}</span>
                <span>服务：{{ serviceTypes(order.service_types).join('、') || '未指定' }}</span>
                <span>{{ order.problem_description || '' }}</span>
                <span class="tech-card-action">{{ order.status === 'assigned' ? '查看并开始' : '继续维修' }}</span>
              </button>
              <div v-if="!activeOrders.length" class="empty">暂无当前维修单</div>
            </div>
          </section>
          <details class="tech-history">
            <summary>历史记录 <span>{{ historyOrders.length }} 单</span></summary>
            <div class="order-list">
              <article class="order-item" v-for="order in historyOrders" :key="order.id">
                <strong>{{ order.order_no }}</strong>
                <span>{{ statusLabel(order.status) }} · {{ order.activity_name || '' }}</span>
                <p>{{ deviceLine(order) }}</p><p>{{ techOwnerLine(order) }}</p><p>{{ order.problem_description || '' }}</p>
              </article>
              <div v-if="!historyOrders.length" class="empty">暂无历史记录</div>
            </div>
          </details>
        </section>
        <div v-if="detailOrder" class="modal" aria-hidden="false" @keydown.esc="closeOrder">
          <div class="modal-backdrop" @click="closeOrder"></div>
          <section ref="techRepairModal" class="modal-panel modal-panel-wide">
            <div class="section-head">
              <div><p class="eyebrow">{{ statusLabel(detailOrder.status) }}</p><h1>{{ detailOrder.order_no }}</h1></div>
              <button class="button" type="button" @click="closeOrder">关闭</button>
            </div>
            <div class="detail-body">
              <section class="detail-block">
                <h2>订单信息</h2>
                <div class="detail-grid">
                  <div class="field-row" v-for="row in [['机主', techOwnerLine(detailOrder)], ['备用电话', detailOrder.backup_phone || '未填'], ['QQ', detailOrder.customer_qq || '未填'], ['性别', detailOrder.gender || '未填'], ['学院', detailOrder.college || '未填'], ['宿舍', detailOrder.dormitory || '未填'], ['学号', detailOrder.student_id || '未填'], ['设备', deviceLine(detailOrder)], ['开机密码', detailOrder.boot_password || '未填'], ['外带附件', detailOrder.accessories || '未填'], ['外观及硬件损坏', detailOrder.existing_damage || '未填'], ['录入位置', detailOrder.station_no ? detailOrder.station_no + '号位' : '未填'], ['活动', [detailOrder.activity_name, detailOrder.activity_date, detailOrder.activity_location].filter(Boolean).join(' · ') || '未填'], ['服务', serviceTypes(detailOrder.service_types).join('、') || '未指定'], ['确认过保', Number(detailOrder.confirmed_out_of_warranty) === 1 ? '已确认' : '未确认'], ['允许重装系统', boolText(detailOrder.allow_reinstall)], ['允许清空硬盘', boolText(detailOrder.allow_format)], ['重要数据', detailOrder.important_data || '未填'], ['故障描述', detailOrder.problem_description || '未填'], ['维修要求', detailOrder.repair_notes || '未填'], ['备注', detailOrder.notes || '未填'], ['创建时间', detailOrder.created_at || ''], ['更新时间', detailOrder.updated_at || '']]" :key="row[0]"><span>{{ row[0] }}</span><strong>{{ row[1] }}</strong></div>
                </div>
              </section>
              <section class="detail-block" v-if="detailOrder.status === 'assigned'">
                <h2>维修操作</h2>
                <div class="form-actions"><button class="button primary" type="button" @click="start(detailOrder, $event)">开始维修</button></div>
              </section>
              <section class="detail-block" v-else-if="detailOrder.status === 'repairing'">
                <h2>维修记录</h2>
                <form class="form repair-form" @submit.prevent="submitRepair">
                  <input type="hidden" name="order_id" :value="detailOrder.id">
                  <label>故障诊断<textarea name="diagnosis" required rows="3"></textarea></label>
                  <label>解决方案<textarea name="solution" required rows="3"></textarea></label>
                  <label>维修结果<select name="result"><option value="fixed">已修复</option><option value="partially_fixed">部分修复</option><option value="unfixed">未修复</option><option value="needs_followup">需后续处理</option></select></label>
                  <label>备注<textarea name="notes" rows="2"></textarea></label>
                  <div class="signature-pad" data-signature-pad><div class="signature-head"><strong>技术员签字</strong><button class="button" type="button" data-signature-clear>清除</button></div><canvas></canvas><input type="hidden" name="technician_signature"></div>
                  <div class="form-actions"><button class="button primary" type="submit">提交维修记录</button></div>
                </form>
              </section>
            </div>
          </section>
        </div>
      </main>
    `
  };

  const WechatOpenPage = {
    props: ['entry', 'next'],
    data() {
      return { copyState: 'copying' };
    },
    computed: {
      forceEntry() {
        try {
          const url = new URL(this.entry, window.location.href);
          url.searchParams.set('force_oauth', '1');
          return url.toString();
        } catch {
          return this.entry || '#/';
        }
      }
    },
    methods: {
      tryOpenWechat() {
        window.location.href = 'weixin://';
      },
      async copyEntry() {
        const value = this.entry || `${APP_BASE}/index.html#/tech`;
        try {
          if (!navigator.clipboard?.writeText) throw new Error('clipboard unavailable');
          await navigator.clipboard.writeText(value);
        } catch {
          const input = document.createElement('textarea');
          input.value = value;
          document.body.appendChild(input);
          input.select();
          const ok = document.execCommand('copy');
          input.remove();
          if (!ok) {
            this.copyState = 'failed';
            return;
          }
        }
        this.copyState = 'copied';
      },
      renderQr() {
        const target = this.$refs.qr;
        if (!target || !window.QRCode || !this.entry) return;
        target.innerHTML = '';
        new QRCode(target, {
          text: this.entry,
          width: 220,
          height: 220,
          colorDark: '#172033',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      }
    },
    mounted() {
      if (/MicroMessenger/i.test(navigator.userAgent)) {
        window.location.replace(this.entry);
        return;
      }
      nextTick(() => this.renderQr());
      this.copyEntry();
    },
    template: `
      <main class="shell compact">
        <section class="card">
          <h1>监测到您不在微信内</h1>
          <p v-if="copyState === 'copied'" class="muted">链接已复制，请在微信内打开</p>
          <p v-else-if="copyState === 'failed'" class="muted">请复制入口后在微信内打开</p>
          <p v-else class="muted">正在复制入口链接</p>
          <div ref="qr" class="qr-box"></div>
          <div class="actions">
            <button class="button primary" type="button" @click="tryOpenWechat">打开微信</button>
            <button class="button" type="button" @click="copyEntry">{{ copyState === 'copied' ? '已复制' : '复制入口' }}</button>
            <a class="button" :href="forceEntry">继续</a>
          </div>
        </section>
      </main>
    `
  };

  const AdminPage = {
    props: ['staff', 'adminTab', 'routeParams'],
    data() {
      const params = this.routeParams || {};
      return {
        tab: normalizeAdminTab(this.adminTab),
        activities: [],
        selectedActivityId: Number(params.activity_id || 0) || null,
        orders: [],
        technicians: [],
        shiftCodes: [],
        q: params.q || '',
        status: params.status || '',
        detail: null,
        editOrder: null,
        cancelTarget: null,
        message: null,
        openActionOrderId: null,
        actionPopoverStyle: {}
      };
    },
    computed: {
      selectedActivity() {
        return this.activities.find((item) => Number(item.id) === Number(this.selectedActivityId)) || null;
      },
      currentActionOrder() {
        if (!this.openActionOrderId) return null;
        return this.orders.find((order) => Number(order.id) === Number(this.openActionOrderId)) || null;
      }
    },
    methods: {
      statusLabel,
      serviceTypes,
      ownerLine,
      deviceLine,
      assignmentStatusLabel,
      repairResultLabel,
      smsStatusLabel,
      adminHref(tab) {
        return hashFor(`/admin/${normalizeAdminTab(tab)}`);
      },
      adminOrdersPath(activityId = this.selectedActivityId) {
        const params = new URLSearchParams();
        if (activityId) params.set('activity_id', String(activityId));
        if (this.status) params.set('status', this.status);
        if (this.q) params.set('q', this.q);
        const suffix = params.toString();
        return `/admin/orders${suffix ? `?${suffix}` : ''}`;
      },
      adminOrdersHref(activity) {
        return hashFor(this.adminOrdersPath(activity?.id || this.selectedActivityId));
      },
      batchExportHref() {
        const params = new URLSearchParams({ route: 'orders.export', current: '0', limit: '500' });
        if (this.selectedActivityId) params.set('activity_id', String(this.selectedActivityId));
        if (this.status) params.set('status', this.status);
        if (this.q) params.set('q', this.q);
        return `api/index.php?${params.toString()}`;
      },
      orderExportHref(order) {
        return `api/index.php?route=orders.export&order_id=${Number(order.id)}`;
      },
      submitOrderSearch() {
        const path = this.adminOrdersPath(this.selectedActivityId);
        if (window.location.hash === hashFor(path)) {
          this.loadOrders();
          return;
        }
        go(path);
      },
      async load() {
        const [activities, technicians, shiftCodes] = await Promise.all([
          api('activities.list', null, 'GET'),
          api('technicians.list', null, 'GET'),
          api('shift_codes.list', null, 'GET')
        ]);
        if (activities.success) {
          this.activities = activities.data.activities || [];
          if (!this.selectedActivityId && this.activities.length) {
            const current = this.activities.find((item) => Number(item.is_current) === 1);
            this.selectedActivityId = Number((current || this.activities[0]).id);
          }
          await this.loadOrders();
        }
        this.technicians = technicians.success ? (technicians.data.technicians || []) : [];
        this.shiftCodes = shiftCodes.success ? (shiftCodes.data.shift_codes || []) : [];
      },
      async loadOrders() {
        if (!this.selectedActivityId) return;
        this.openActionOrderId = null;
        const params = new URLSearchParams({ current: '0', limit: '500', activity_id: String(this.selectedActivityId), q: this.q || '', status: this.status || '' });
        const data = await api(`orders.list&${params.toString()}`, null, 'GET');
        if (data.success) this.orders = data.data.orders || [];
        else showGlobalMessage(data);
      },
      toggleOrderActions(order, event) {
        if (Number(this.openActionOrderId) === Number(order.id)) {
          this.closeOrderActions();
          return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        const menuWidth = 126;
        const menuHeight = 176;
        const gap = 8;
        const left = Math.min(Math.max(10, rect.right - menuWidth), window.innerWidth - menuWidth - 10);
        const opensUp = rect.bottom + menuHeight > window.innerHeight && rect.top > menuHeight;
        const top = opensUp ? rect.top - menuHeight - gap : rect.bottom + gap;
        this.actionPopoverStyle = { left: `${left}px`, top: `${Math.max(10, top)}px` };
        this.openActionOrderId = Number(order.id);
      },
      closeOrderActions() {
        this.openActionOrderId = null;
        this.actionPopoverStyle = {};
      },
      handleOutsideActionClick(event) {
        if (!event.target.closest?.('.action-popover-wrap, .floating-action-menu')) this.closeOrderActions();
      },
      async setCurrent(activity, event) {
        const data = await runWithButton(event.currentTarget, () => api('activities.set_current', { activity_id: activity.id }), '设置中...');
        showGlobalMessage(data);
        if (data.success) this.load();
      },
      async clearCurrent(event) {
        const data = await runWithButton(event.currentTarget, () => api('activities.clear_current', {}), '取消中...');
        showGlobalMessage(data);
        if (data.success) this.load();
      },
      async openDetail(order) {
        this.closeOrderActions();
        const data = await api(`orders.detail&id=${Number(order.id)}`, null, 'GET');
        if (data.success) this.detail = data.data;
        else showGlobalMessage(data);
      },
      async openEdit(order) {
        this.closeOrderActions();
        const data = await api(`orders.detail&id=${Number(order.id)}`, null, 'GET');
        if (data.success) this.editOrder = data.data.order;
        else showGlobalMessage(data);
      },
      async submitEdit(event) {
        const data = await runWithButton(event.submitter, () => api('orders.update', formToPayload(event.currentTarget)), '保存中...');
        showGlobalMessage(data);
        if (data.success) {
          this.editOrder = null;
          this.loadOrders();
        }
      },
      openCancel(order) {
        this.closeOrderActions();
        this.cancelTarget = order;
      },
      async submitCancel(event) {
        const data = await runWithButton(event.submitter, () => api('orders.cancel', formToPayload(event.currentTarget)), '取消中...');
        showGlobalMessage(data);
        if (data.success) {
          this.cancelTarget = null;
          this.loadOrders();
        }
      },
      async createActivity(event) {
        const data = await runWithButton(event.submitter, () => api('activities.create', formToPayload(event.currentTarget)), '创建中...');
        showGlobalMessage(data);
        if (data.success) {
          event.currentTarget.reset();
          go('/admin/orders');
        }
      },
      async createShiftCode(event) {
        const data = await runWithButton(event.submitter, () => api('shift_codes.create', formToPayload(event.currentTarget)), '保存中...');
        showGlobalMessage(data);
        if (data.success) { event.currentTarget.reset(); this.load(); }
      },
      async disableShiftCode(code, event) {
        const data = await runWithButton(event.currentTarget, () => api('shift_codes.disable', { id: code.id }), '禁用中...');
        showGlobalMessage(data);
        if (data.success) this.load();
      },
      async createTechnician(event) {
        const data = await runWithButton(event.submitter, () => api('technicians.create', formToPayload(event.currentTarget)), '新增中...');
        showGlobalMessage(data);
        if (data.success) {
          event.currentTarget.reset();
          const list = await api('technicians.list', null, 'GET');
          if (list.success) this.technicians = list.data.technicians || [];
        }
      },
      async importTechnicians(event) {
        const data = await runWithButton(event.currentTarget, () => api('technicians.import_fy_users', {}), '导入中...');
        if (data.success && data.data) data.message = `导入 ${data.data.imported_count} 人，跳过 ${data.data.skipped_count} 人`;
        showGlobalMessage(data);
        if (data.success) this.load();
      },
      async bindToken(tech, event) {
        const data = await runWithButton(event.currentTarget, () => api('technicians.bind_token', { technician_id: tech.id }), '生成中...');
        if (data.success) data.message = `绑定码：${data.data.token}，${data.data.expires_in_minutes} 分钟内有效`;
        showGlobalMessage(data);
      }
    },
    mounted() {
      document.addEventListener('click', this.handleOutsideActionClick);
      window.addEventListener('resize', this.closeOrderActions);
      window.addEventListener('scroll', this.closeOrderActions, true);
      this.load();
    },
    unmounted() {
      document.removeEventListener('click', this.handleOutsideActionClick);
      window.removeEventListener('resize', this.closeOrderActions);
      window.removeEventListener('scroll', this.closeOrderActions, true);
    },
    template: `
      <main class="admin-shell">
        <header class="topbar"><a href="#/workbench">飞大修</a><span>管理后台</span></header>
        <div class="admin-layout">
          <aside class="admin-nav">
            <a class="admin-tab" :class="{active: tab === 'orders'}" :href="adminHref('orders')">活动与订单</a>
            <a class="admin-tab" :class="{active: tab === 'settings'}" :href="adminHref('settings')">活动设置</a>
            <a class="admin-tab" :class="{active: tab === 'shift'}" :href="adminHref('shift')">值班码</a>
            <a class="admin-tab" :class="{active: tab === 'technicians'}" :href="adminHref('technicians')">技术员</a>
          </aside>
          <section class="admin-main">
            <section v-if="tab === 'orders'" class="admin-panel active">
              <div class="section-head"><h1>活动与订单</h1><div class="topbar-actions"><a v-if="selectedActivityId" class="button" :href="batchExportHref()">批量导出</a><button class="button" type="button" @click="load">刷新</button></div></div>
              <div class="admin-split">
                <section class="admin-column">
                  <div class="subhead"><h2>活动</h2></div>
                  <article v-for="activity in activities" :key="activity.id" class="admin-row" :class="{ selected: Number(activity.id) === Number(selectedActivityId) }">
                    <a class="admin-row-main" :href="adminOrdersHref(activity)"><strong>{{ activity.name }}</strong><span>{{ activity.activity_date }} · {{ Number(activity.order_count || 0) }} 单</span></a>
                    <button class="button" :class="{ primary: Number(activity.is_current) !== 1, 'danger-button': Number(activity.is_current) === 1 }" type="button" @click="Number(activity.is_current) === 1 ? clearCurrent($event) : setCurrent(activity, $event)">{{ Number(activity.is_current) === 1 ? '取消当前' : '设为当前' }}</button>
                  </article>
                </section>
                <section class="admin-column wide">
                  <div class="section-head"><h2>{{ selectedActivity ? selectedActivity.name : '订单' }}</h2><span class="muted">{{ selectedActivity ? selectedActivity.activity_date : '' }}</span></div>
                  <form class="toolbar" @submit.prevent="submitOrderSearch"><input v-model="q" placeholder="订单号、姓名、手机号、设备、故障"><select v-model="status"><option value="">全部状态</option><option value="waiting_assignment">待技术员接机</option><option value="assigned">已分配</option><option value="repairing">维修中</option><option value="ready_for_pickup">待通知</option><option value="notified">待取机</option><option value="completed">已完成</option><option value="cancelled">已取消</option></select><button class="button primary" type="submit">查询</button></form>
                  <div class="admin-table">
                    <div class="table-head"><span>编号</span><span>状态</span><span>机主</span><span>设备</span><span>技术员</span><span>操作</span></div>
                    <article v-for="order in orders" :key="order.id" class="table-row"><strong>{{ order.order_no }}</strong><span class="status-pill">{{ statusLabel(order.status) }}</span><span>{{ ownerLine(order) }}</span><span>{{ deviceLine(order) }}</span><span>{{ order.technician_name || '未分配' }}</span><span class="table-actions"><span class="action-popover-wrap" @click.stop><button class="button action-trigger" type="button" aria-haspopup="menu" :aria-expanded="Number(openActionOrderId) === Number(order.id)" @click="toggleOrderActions(order, $event)">操作</button></span></span></article>
                    <div v-if="!orders.length" class="empty">暂无订单</div>
                  </div>
                </section>
              </div>
            </section>
            <section v-if="tab === 'settings'" class="admin-panel active"><div class="section-head"><h1>活动设置</h1></div><form class="form admin-form" @submit.prevent="createActivity"><div class="form-grid two"><label>活动名称<input name="name" required></label><label>活动日期<input type="date" name="activity_date" required></label><label>地点<input name="location"></label><label>备注<textarea name="notes" rows="2"></textarea></label></div><button class="button primary" type="submit">创建活动</button></form></section>
            <section v-if="tab === 'shift'" class="admin-panel active"><div class="section-head"><h1>值班码</h1></div><form class="form admin-form" @submit.prevent="createShiftCode"><div class="form-grid two"><label>名称<input name="label" placeholder="如：5月大修值班码"></label><label>值班码<input name="code" autocomplete="off" required></label><label>过期时间<input type="datetime-local" name="expires_at"></label></div><button class="button primary" type="submit">保存当前大修值班码</button></form><div class="admin-list"><article v-for="code in shiftCodes" :key="code.id" class="admin-row"><div class="admin-row-main"><strong>{{ code.label }}</strong><span>值班码：{{ code.code_plain || '未记录' }}</span><span>{{ code.status === 'active' ? '启用中' : '已禁用' }} · {{ code.activity_name || '未绑定' }} · 使用 {{ Number(code.use_count || 0) }} 次</span><span>过期：{{ code.expires_at || '不过期' }}</span></div><button v-if="code.status === 'active'" class="button" type="button" @click="disableShiftCode(code, $event)">禁用</button></article></div></section>
            <section v-if="tab === 'technicians'" class="admin-panel active"><div class="section-head"><h1>技术员</h1><button class="button primary" type="button" @click="importTechnicians">从 fy_users 导入</button></div><form class="form admin-form" @submit.prevent="createTechnician($event)"><div class="form-grid three"><label>姓名<input name="display_name" required></label><label>手机号<input name="phone" inputmode="numeric"></label><label>校区<input name="campus"></label></div><button class="button primary" type="submit">新增技术员</button></form><div class="admin-list"><article v-for="tech in technicians" :key="tech.id" class="admin-row"><div class="admin-row-main"><strong>{{ tech.display_name }}</strong><span>{{ tech.phone || '未填手机号' }} · {{ tech.campus || '未填校区' }}</span><span>{{ tech.bound_at ? '已绑定微信' : '未绑定微信' }} · 当前活跃订单 {{ Number(tech.active_orders || 0) }} 单</span></div><button class="button" type="button" @click="bindToken(tech, $event)">生成绑定码</button></article></div></section>
          </section>
        </div>
        <div v-if="editOrder" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="editOrder = null"></div>
          <section class="modal-panel modal-panel-wide">
            <div class="section-head"><h1>编辑订单 {{ editOrder.order_no }}</h1><button class="button" type="button" @click="editOrder = null">关闭</button></div>
            <form class="form intake-form" @submit.prevent="submitEdit">
              <input type="hidden" name="order_id" :value="editOrder.id">
              <div class="form-strip"><label>录入位置<select name="position" :value="editOrder.station_no" required><option value="1">1号位</option><option value="4">4号位</option></select></label><label>维修编号<input type="text" :value="editOrder.order_no" readonly></label></div>
              <div class="form-grid two">
                <section class="form-section">
                  <h2>机主个人基本信息</h2>
                  <label>姓名<input type="text" name="customer_name" :value="editOrder.customer_name" required></label>
                  <div class="field-group"><span>性别</span><div class="choice-row"><label><input type="radio" name="gender" value="男" :checked="editOrder.gender === '男'"> 男</label><label><input type="radio" name="gender" value="女" :checked="editOrder.gender === '女'"> 女</label></div></div>
                  <label>手机号<input type="tel" name="customer_phone" :value="editOrder.customer_phone" autocomplete="tel" inputmode="numeric" required></label>
                  <label>备用联系人手机号<input type="tel" name="backup_phone" :value="editOrder.backup_phone" inputmode="numeric"></label>
                  <label>就读学院<input type="text" name="college" :value="editOrder.college"></label>
                  <label>居住宿舍<input type="text" name="dormitory" :value="editOrder.dormitory"></label>
                  <label>学号<input type="text" name="student_id" :value="editOrder.student_id"></label>
                  <label>QQ号码<input type="text" name="customer_qq" :value="editOrder.customer_qq" inputmode="numeric"></label>
                </section>
                <section class="form-section">
                  <h2>待修电脑基本信息</h2>
                  <label>设备类型<select name="device_type" :value="editOrder.device_type" required><option value="台式机">台式机</option><option value="笔记本">笔记本</option><option value="一体机">一体机</option><option value="其他">其他</option></select></label>
                  <label>品牌与型号<input type="text" name="device_model" :value="editOrder.device_model"></label>
                  <label>开机密码<input type="text" name="login_password" :value="editOrder.boot_password" autocomplete="off"></label>
                  <label>外带附件<textarea name="accessories" rows="2" maxlength="200" :value="editOrder.accessories"></textarea></label>
                  <label>电脑已经存在的外观及硬件损坏与缺陷<textarea name="existing_damage" rows="2" maxlength="200" :value="editOrder.existing_damage"></textarea></label>
                  <div class="field-group required-confirm"><span>确认过保</span><label class="check-line"><input type="checkbox" name="confirmed_out_of_warranty" value="1" :checked="Number(editOrder.confirmed_out_of_warranty) === 1" required> 已确认设备过保</label></div>
                </section>
              </div>
              <section class="form-section">
                <h2>所需服务</h2>
                <div class="checkbox-grid">
                  <label><input type="checkbox" name="service_type[]" value="硬件初级检修" :checked="serviceTypes(editOrder.service_types).includes('硬件初级检修')"> 硬件初级检修</label>
                  <label><input type="checkbox" name="service_type[]" value="拆机清洁" :checked="serviceTypes(editOrder.service_types).includes('拆机清洁')"> 拆机清洁</label>
                  <label><input type="checkbox" name="service_type[]" value="数据恢复" :checked="serviceTypes(editOrder.service_types).includes('数据恢复')"> 数据恢复</label>
                  <label><input type="checkbox" name="service_type[]" value="软件、驱动安装" :checked="serviceTypes(editOrder.service_types).includes('软件、驱动安装')"> 软件、驱动安装</label>
                  <label><input type="checkbox" name="service_type[]" value="病毒清除、系统修复" :checked="serviceTypes(editOrder.service_types).includes('病毒清除、系统修复')"> 病毒清除、系统修复</label>
                  <label><input type="checkbox" name="service_type[]" value="系统优化" :checked="serviceTypes(editOrder.service_types).includes('系统优化')"> 系统优化</label>
                </div>
                <div class="form-grid two">
                  <div class="field-group"><span>若维修进行中有必要是否可以采用系统重装</span><div class="choice-row"><label><input type="radio" name="allow_system_reinstall" value="是" :checked="Number(editOrder.allow_reinstall) === 1"> 是</label><label><input type="radio" name="allow_system_reinstall" value="否" :checked="Number(editOrder.allow_reinstall) === 0"> 否</label></div></div>
                  <div class="field-group"><span>在进行系统重装操作时是否可以清空硬盘</span><div class="choice-row"><label><input type="radio" name="allow_disk_format" value="是" :checked="Number(editOrder.allow_format) === 1"> 是</label><label><input type="radio" name="allow_disk_format" value="否" :checked="Number(editOrder.allow_format) === 0"> 否</label></div></div>
                </div>
                <label>故障描述 <span class="required">*</span><textarea name="problem_description" rows="3" required maxlength="500" :value="editOrder.problem_description"></textarea></label>
                <label>需要进行备份的重要数据<textarea name="important_data" rows="2" maxlength="200" :value="editOrder.important_data"></textarea></label>
                <label>对于故障或维修要求的补充描述<textarea name="repair_notes" rows="2" maxlength="200" :value="editOrder.repair_notes"></textarea></label>
              </section>
              <label>备注<textarea name="notes" rows="2" maxlength="200" :value="editOrder.notes"></textarea></label>
              <div class="form-actions"><button class="button" type="button" @click="editOrder = null">取消</button><button class="button primary" type="submit">保存订单</button></div>
            </form>
          </section>
        </div>
        <teleport to="body">
          <div v-if="currentActionOrder" class="floating-action-menu" :style="actionPopoverStyle" role="menu" @click.stop>
            <button class="button" type="button" role="menuitem" @click="openDetail(currentActionOrder)">详情</button>
            <button class="button" type="button" role="menuitem" @click="openEdit(currentActionOrder)">编辑</button>
            <a class="button" role="menuitem" :href="orderExportHref(currentActionOrder)" @click="closeOrderActions">导出</a>
            <button v-if="!['cancelled', 'completed'].includes(currentActionOrder.status)" class="button danger-button" type="button" role="menuitem" @click="openCancel(currentActionOrder)">取消</button>
          </div>
        </teleport>
        <div v-if="cancelTarget" class="modal" aria-hidden="false">
          <div class="modal-backdrop" @click="cancelTarget = null"></div>
          <section class="modal-panel">
            <div class="section-head"><h1>取消订单 {{ cancelTarget.order_no }}</h1><button class="button" type="button" @click="cancelTarget = null">关闭</button></div>
            <form class="form" @submit.prevent="submitCancel">
              <input type="hidden" name="order_id" :value="cancelTarget.id">
              <label>取消原因<textarea name="reason" rows="3"></textarea></label>
              <div class="form-actions"><button class="button" type="button" @click="cancelTarget = null">返回</button><button class="button danger-button" type="submit">确认取消</button></div>
            </form>
          </section>
        </div>
        <div v-if="detail" class="modal" aria-hidden="false"><div class="modal-backdrop" @click="detail = null"></div><section class="modal-panel modal-panel-wide"><div class="section-head"><h1>订单 {{ detail.order.order_no }}</h1><button class="button" type="button" @click="detail = null">关闭</button></div><div class="detail-body"><section class="detail-block"><h2>订单信息</h2><div class="detail-grid"><div class="field-row" v-for="row in [['机主', ownerLine(detail.order)], ['手机号', detail.order.customer_phone], ['设备', deviceLine(detail.order)], ['状态', statusLabel(detail.order.status)], ['开机密码', detail.order.boot_password || '未填'], ['确认过保', Number(detail.order.confirmed_out_of_warranty) === 1 ? '已确认' : '未确认'], ['故障描述', detail.order.problem_description], ['维修要求', detail.order.repair_notes || '未填'], ['创建时间', detail.order.created_at]]" :key="row[0]"><span>{{ row[0] }}</span><strong>{{ row[1] }}</strong></div></div></section><section class="detail-block"><h2>技术员分配</h2><div class="record-list"><article v-for="row in detail.assignments" class="record-item"><strong>{{ row.technician_name }}</strong><span>{{ assignmentStatusLabel(row.status) }} · {{ row.assigned_at }}</span><p>{{ row.notes || '' }}</p></article><div v-if="!detail.assignments.length" class="empty">暂无记录</div></div></section><section class="detail-block"><h2>维修记录</h2><div class="record-list"><article v-for="row in detail.repair_records" class="record-item"><strong>{{ row.technician_name }}</strong><span>{{ repairResultLabel(row.result) }} · {{ row.submitted_at }}</span><p>诊断：{{ row.diagnosis }}</p><p>方案：{{ row.solution }}</p><p>{{ row.notes || '' }}</p><img v-if="row.technician_signature" class="signature-preview" :src="row.technician_signature" alt="技术员签字"></article><div v-if="!detail.repair_records.length" class="empty">暂无记录</div></div></section><section class="detail-block"><h2>取机记录</h2><div class="record-list"><article v-for="row in detail.pickup_records" class="record-item"><strong>{{ row.pickup_time }}</strong><span>{{ row.handler_name || '系统/旧系统' }}</span><p>{{ row.notes || '' }}</p><img v-if="row.customer_signature" class="signature-preview" :src="row.customer_signature" alt="机主签字"></article><div v-if="!detail.pickup_records.length" class="empty">暂无记录</div></div></section><section class="detail-block"><h2>短信记录</h2><div class="record-list"><article v-for="row in detail.sms_logs" class="record-item"><strong>{{ smsStatusLabel(row.status) }}</strong><span>{{ row.sent_at || row.created_at }}</span><p>{{ row.phone_mask }} · {{ row.content }}</p></article><div v-if="!detail.sms_logs.length" class="empty">暂无记录</div></div></section></div></section></div>
      </main>
    `
  };

  const components = {
    home: HomePage,
    query: CustomerQueryPage,
    login: StaffLoginPage,
    adminLogin: AdminLoginPage,
    workbench: WorkbenchPage,
    intake: IntakePage,
    dispatch: DispatchPage,
    service: ServicePage,
    tech: TechPage,
    wechatOpen: WechatOpenPage,
    admin: AdminPage
  };

  createApp({
    components: { Topbar },
    data() {
      return {
        route: routeFromHash(),
        auth: { staff: null, technician: null, wechat: null },
        authLoaded: false
      };
    },
    computed: {
      pageComponent() {
        if (!this.authLoaded) return null;
        return components[this.route.page] || HomePage;
      },
      pageProps() {
        const staff = this.auth.staff;
        const claim = this.route.params.claim || '';
        if (this.route.page === 'workbench') {
          return {
            staff,
            roleLabel: roleLabelFor(staff),
            canUseOnsite: ['admin', 'duty'].includes(staff?.role || ''),
            stations: stationsFor(staff)
          };
        }
        if (this.route.page === 'tech') {
          return {
            technician: this.auth.technician,
            wechat: this.auth.wechat,
            error: this.route.params.error || '',
            claim,
            techNext: wechatStartHref(claim ? { claim } : {})
          };
        }
        if (this.route.page === 'admin') {
          return { staff, adminTab: this.route.adminTab, routeParams: this.route.params };
        }
        if (this.route.page === 'wechatOpen') {
          return {
            entry: this.route.params.entry || wechatStartHref(claim ? { claim } : {}),
            next: this.route.params.next || techNext(claim ? { claim } : {})
          };
        }
        return { staff };
      }
    },
    methods: {
      async loadAuth(options = {}) {
        const guard = options.guard !== false;
        const silent = options.silent === true;
        const data = await api('auth.me', null, 'GET');
        if (data.success) {
          this.auth = {
            staff: data.data?.staff || null,
            technician: data.data?.technician || null,
            wechat: data.data?.wechat || null
          };
        } else {
          if (!silent) {
            this.auth = { staff: null, technician: null, wechat: null };
            showGlobalMessage(data);
          }
        }
        this.authLoaded = true;
        if (guard) this.guardRoute();
        return this.auth;
      },
      syncRoute() {
        this.route = routeFromHash();
        this.guardRoute();
      },
      guardRoute() {
        if (!this.authLoaded) return;
        const staffPages = ['workbench', 'intake', 'dispatch', 'service'];
        if (staffPages.includes(this.route.page) && !this.auth.staff) {
          go('/login', true);
          return;
        }
        if (this.route.page === 'admin' && !this.auth.staff) {
          go('/admin-login', true);
          return;
        }
        if (this.route.page === 'admin' && this.auth.staff?.role !== 'admin') {
          go('/workbench', true);
          return;
        }
        if (this.route.page === 'admin' && this.route.slug === 'admin') {
          go('/admin/orders', true);
          return;
        }
        const role = this.auth.staff?.role || '';
        const routeRoles = {
          intake: ['admin', 'duty', 'intake'],
          dispatch: ['admin', 'duty', 'dispatcher'],
          service: ['admin', 'duty', 'service']
        };
        if (routeRoles[this.route.page] && !routeRoles[this.route.page].includes(role)) {
          go('/workbench', true);
          return;
        }
        if (this.route.page === 'tech' && !this.route.params.wx && !this.route.params.error) {
          window.location.href = wechatStartHref(this.route.params.claim ? { claim: this.route.params.claim } : {});
          return;
        }
        if (this.route.page === 'tech' && !this.auth.technician && !this.auth.wechat && !this.route.params.error) {
          window.location.href = wechatStartHref(this.route.params.claim ? { claim: this.route.params.claim } : {});
        }
      }
    },
    mounted() {
      window.FDX_REFRESH_AUTH = (options) => this.loadAuth(options);
      window.FDX_GET_AUTH = () => this.auth;
      window.addEventListener('hashchange', this.syncRoute);
      if (!window.location.hash) {
        go('/', true);
      }
      this.loadAuth();
    },
    unmounted() {
      window.removeEventListener('hashchange', this.syncRoute);
      delete window.FDX_REFRESH_AUTH;
      delete window.FDX_GET_AUTH;
    },
    template: `
      <div>
        <main v-if="!authLoaded" class="shell compact"><section class="card"><h1>飞大修</h1></section></main>
        <component v-if="pageComponent" :is="pageComponent" v-bind="pageProps" :key="route.slug + ':' + route.queryString"></component>
      </div>
    `
  }).component('Topbar', Topbar).component('CurrentOrdersPanel', CurrentOrdersPanel).mount('#vueApp');
})();
