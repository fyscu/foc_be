<?php
session_name('admin');
session_start();
if (empty($_SESSION['admin_logged_in'])) {
  header('Location: login');
  exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>工单管理</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<?php
$role = $_SESSION['admin_role'] ?? '';
if ($role !== 'super') {
    echo '<body class="d-flex justify-content-center align-items-center vh-100 bg-light"><div class="alert alert-danger text-center shadow" role="alert"><h4 class="alert-heading">权限不足</h4><p>当前管理员账号无权限访问该页面。</p></div></body></html>';
    exit;
}
?>
<body class="p-4">
   
  <h3 class="mb-4">工单管理</h3>

  <!-- 搜索与筛选 -->
  <form id="searchForm" class="row g-3 mb-4">
    <div class="col-md-3">
      <input type="text" class="form-control" name="keyword" placeholder="工单号 / 用户昵称 / 技术员昵称 / 手机号">
    </div>
    <div class="col-md-2">
      <select class="form-select" name="repair_status">
        <option value="">全部状态</option>
        <option value="Pending">待分配</option>
        <option value="Repairing">维修中</option>
        <option value="UserConfirming">待用户确认</option>
        <option value="TechConfirming">待技工确认</option>
        <option value="Done">完成</option>
        <option value="Canceled">已取消</option>
        <option value="Closed">已关闭</option>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="campus">
        <option value="">全部校区</option>
        <option value="江安">江安</option>
        <option value="望江">望江</option>
        <option value="华西">华西</option>
        <option value="线下">线下</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">搜索</button>
    </div>
  </form>

  <!-- 表格 -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead>
        <tr>
          <th><a href="#" class="sort-link" data-field="id">工单号</a></th>
          <th>用户昵称</th>
          <th>技术员昵称</th>
          <th>手机号</th>
          <th>状态</th>
          <th>校区</th>
          <th><a href="#" class="sort-link" data-field="create_time">创建时间</a></th>
          <th><a href="#" class="sort-link" data-field="assigned_time">分配时间</a></th>
          <th><a href="#" class="sort-link" data-field="completion_time">完成时间</a></th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <tr><td colspan="8" class="text-center">加载中...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-3" id="paginationBar">
    <div>
      <button id="prevPage" class="btn btn-sm btn-outline-secondary">« 上一页</button>
      <select id="pageSelect" class="form-select d-inline-block" style="width: auto;"></select>
      <button id="nextPage" class="btn btn-sm btn-outline-secondary">下一页 »</button>
    </div>
    <div id="resultInfo" class="text-muted small mt-2 text-end"></div>
    <div>
      <label class="me-1">每页条数:</label>
      <select id="limitSelect" class="form-select d-inline-block" style="width: auto;">
        <option value="10">10</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="200">200</option>
      </select>
    </div>
  </div>

  <!-- 编辑浮窗 -->
  <div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="orderForm">
          <div class="modal-header">
            <h5 class="modal-title">工单状态修改</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id" id="orderId">
            <label class="form-label">工单状态</label>
            <select class="form-select" name="repair_status" id="repairStatus">
              <option value="Pending">待分配</option>
              <option value="Repairing">维修中</option>
              <option value="UserConfirming">待用户确认</option>
              <option value="TechConfirming">待技工确认</option>
              <option value="Done">完成</option>
              <option value="Canceled">已取消</option>
              <option value="Closed">已关闭</option>
            </select>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" id="deleteBtn">删除</button>
            <button type="submit" class="btn btn-primary">保存</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const notyf = new Notyf();
    let currentPage = 1;
    let limit = 10;
    let sortField = 'id';
    let sortOrder = 'desc';

    const statusMap = {
      'Pending': '待分配',
      'Repairing': '维修中',
      'UserConfirming': '待用户确认',
      'TechConfirming': '待技工确认',
      'Done': '完成',
      'Canceled': '已取消',
      'Closed': '已关闭'
    };

    async function fetchOrders(page = 1) {
        
      currentPage = page;
      const formData = new FormData(document.getElementById('searchForm'));
      formData.append('page', page);
      formData.append('limit', limit);
      formData.append('sort', sortField);
      formData.append('order', sortOrder);

      const params = new URLSearchParams(formData);
      const res = await fetch(`api/getTicket_admin?${params.toString()}`);
      const data = await res.json();

      const tbody = document.getElementById('tableBody');
      tbody.innerHTML = '';
      if (!data.rows || data.rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">暂无数据</td></tr>';
        return;
      }

      for (const row of data.rows) {
        tbody.innerHTML += `
          <tr data-order='${JSON.stringify(row)}'>
            <td>${row.id}</td>
            <td>${row.user_nick || '神秘用户'}${"(" + row.user_id + ")" || ''}</td>
            <td>${row.tech_nick || '未分配'}${"(" + row.tech_id + ")" || ' '}</td>
            <td>${row.user_phone || ''}</td>
            <td>${statusMap[row.repair_status] || row.repair_status}</td>
            <td>${row.campus}</td>
            <td>${row.create_time || ''}</td>
            <td>${row.assigned_time || ''}</td>
            <td>${row.completion_time || ''}</td>
          </tr>`;
      }
      const start = (currentPage - 1) * limit + 1;
      const end = Math.min(currentPage * limit, data.total);
      document.getElementById('resultInfo').textContent = `第 ${start}–${end} 条，共 ${data.total} 条`;
      // console.log([...formData.entries()]);
      renderPagination(data.total, page);
    }

    function renderPagination(total, current) {
      const totalPages = Math.ceil(total / limit);
      const pageSelect = document.getElementById('pageSelect');
      pageSelect.innerHTML = '';
      for (let i = 1; i <= totalPages; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = `第 ${i} 页`;
        if (i === current) opt.selected = true;
        pageSelect.appendChild(opt);
      }

      document.getElementById('prevPage').disabled = current === 1;
      document.getElementById('nextPage').disabled = current === totalPages;

      pageSelect.onchange = () => fetchOrders(parseInt(pageSelect.value));
      document.getElementById('prevPage').onclick = () => fetchOrders(current - 1);
      document.getElementById('nextPage').onclick = () => fetchOrders(current + 1);
    }

    document.getElementById('limitSelect').addEventListener('change', e => {
      limit = parseInt(e.target.value);
      fetchOrders(1);
    });

    document.getElementById('searchForm').addEventListener('submit', e => {
      e.preventDefault();
      fetchOrders(1);
    });

    document.querySelectorAll('.sort-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const field = link.dataset.field;
        if (sortField === field) {
          sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
          sortField = field;
          sortOrder = 'asc';
        }
        
        fetchOrders(currentPage);
      });
    });

    document.getElementById('tableBody').addEventListener('click', e => {
      const tr = e.target.closest('tr');
      if (!tr || !tr.dataset.order) return;
      const order = JSON.parse(tr.dataset.order);
      document.getElementById('orderId').value = order.id;
      document.getElementById('repairStatus').value = order.repair_status || 'Pending';
      new bootstrap.Modal(document.getElementById('orderModal')).show();
    });
    document.getElementById('deleteBtn').addEventListener('click', async () => {
        const id = document.getElementById('orderId').value;
        if (!confirm('确定要删除此工单？')) return;
        const res = await fetch('api/delete_ticket.php', {
            method: 'POST',
            body: new URLSearchParams({ id })
        });
        const result = await res.json();
        if (result.success) {
            notyf.success('删除成功');
            bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
            fetchOrders(currentPage);
        } else {
            notyf.error('删除失败：' + result.message);
        }
    });
    document.getElementById('orderForm').addEventListener('submit', async e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      const res = await fetch('api/setTicket_admin', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      if (result.success) {
        notyf.success('修改成功');
        bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
        fetchOrders(currentPage);
      } else {
        notyf.error('保存失败：' + result.message);
      }
    });

    fetchOrders();
    
  </script>
</body>
</html>
