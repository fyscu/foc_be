<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin');
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>用户管理</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
  <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
</head>
<?php
$role = $_SESSION['admin_role'] ?? '';
if ($role !== 'super') {
    echo '<body class="d-flex justify-content-center align-items-center vh-100 bg-light"><div class="alert alert-danger text-center shadow" role="alert"><h4 class="alert-heading">权限不足</h4><p>当前管理员账号无权限访问该页面。</p></div></body></html>';
    exit;
}
?>
<body class="p-4">
  <h3 class="mb-4">用户管理</h3>

  <!-- 搜索栏 -->
  <form id="searchForm" class="row g-3 mb-4">
    <div class="col-md-3">
      <input type="text" class="form-control" name="keyword" placeholder="ID / 昵称 / 手机号 / 邮箱">
    </div>
    <div class="col-md-2">
      <select class="form-select" name="role">
        <option value="">全部角色</option>
        <option value="user">普通用户</option>
        <option value="technician">技术员</option>
        <option value="admin">管理员</option>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="campus">
        <option value="">全部校区</option>
        <option value="江安">江安</option>
        <option value="望江">望江</option>
        <option value="华西">华西</option>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select" name="available">
        <option value="">工作状态（仅技术员）</option>
        <option value="0">忙碌</option>
        <option value="1">空闲</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">搜索</button>
    </div>
  </form>

  <!-- 用户表格 -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead id="tableHeader">
        <tr>
          <th><a href="#" class="sort-link" data-field="id">ID</a></th>
          <th><a href="#" class="sort-link" data-field="nickname">昵称</a></th>
          <th>手机号</th>
          <th>邮箱</th>
          <th>角色</th>
          <th>校区</th>
          <th>注册时间</th>
          <th>技术员状态</th>
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

  <!-- 编辑弹窗 -->
  <div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="userForm">
          <div class="modal-header">
            <h5 class="modal-title">用户详情</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body row g-3">
            <input type="hidden" name="id" id="userId">
            <div class="col-md-6">
              <label class="form-label">昵称</label>
              <input type="text" class="form-control" name="nickname" id="nickname">
            </div>
            <div class="col-md-6">
              <label class="form-label">真实姓名</label>
              <input type="text" class="form-control" name="realname" id="realname">
            </div>
            <div class="col-md-6">
              <label class="form-label">手机号</label>
              <input type="text" class="form-control" name="phone" id="phone">
            </div>
            <div class="col-md-6">
              <label class="form-label">邮箱</label>
              <input type="text" class="form-control" name="email" id="email">
            </div>
            <div class="col-md-6">
              <label class="form-label">角色</label>
              <select class="form-select" name="role" id="role">
                <option value="user">用户</option>
                <option value="technician">技术员</option>
                <option value="admin">管理员</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">校区</label>
              <select class="form-select" name="campus" id="campus">
                <option value="江安">江安</option>
                <option value="望江">望江</option>
                <option value="华西">华西</option>
              </select>
            </div>
            <!-- <div class="col-md-6">
              <label class="form-label">技术员状态</label>
              <input type="text" class="form-control" name="techava" id="techava" disabled>
            </div> -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" id="deleteBtn">删除</button>
            <button type="submit" class="btn btn-primary">保存修改</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('limitSelect').addEventListener('change', e => {
    limit = parseInt(e.target.value);
    fetchUsers(1); // 重置到第一页
    });
    const notyf = new Notyf({
    position: {
        x: 'right',
        y: 'top'
    }
    });
    const tableBody = document.getElementById('tableBody');
    const form = document.getElementById('searchForm');
    let sortField = '';
    let sortOrder = 'asc';
    let currentPage = 1;
    let limit = 10;

    async function fetchUsers(page = 1) {
      currentPage = page;
      const formData = new FormData(form);
      const params = new URLSearchParams(formData);
      params.append('page', page);
      params.append('limit', limit);
      if (sortField) {
        params.append('sort', sortField);
        params.append('order', sortOrder);
      }

      const res = await fetch(`api/getUser_admin.php?${params.toString()}`);
      const data = await res.json();
      tableBody.innerHTML = '';

      if (data.rows.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center">暂无数据</td></tr>';
        return;
      }

      for (const user of data.rows) {
        let techava = '';
        if (user.available == '0') {
          techava = '忙碌中';
        } else if (user.available == '1') {
          techava = '空闲';
        } else {
          techava = ' ';
        }
        tableBody.innerHTML += `
          <tr data-user='${JSON.stringify(user)}'>
            <td>${user.id}</td>
            <td>${user.nickname}</td>
            <td>${user.phone}</td>
            <td>${user.email}</td>
            <td>${user.role}</td>
            <td>${user.campus}</td>
            <td>${user.regtime}</td>
            <td>${techava}</td>
          </tr>`;
      }
      const start = (currentPage - 1) * limit + 1;
      const end = Math.min(currentPage * limit, data.total);
      document.getElementById('resultInfo').textContent = `第 ${start}–${end} 条，共 ${data.total} 条`;
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

    // 切换页
    pageSelect.onchange = () => fetchUsers(parseInt(pageSelect.value));
    document.getElementById('prevPage').onclick = () => fetchUsers(current - 1);
    document.getElementById('nextPage').onclick = () => fetchUsers(current + 1);
    }

    form.addEventListener('submit', e => {
      e.preventDefault();
      fetchUsers(1);
    });

    document.querySelectorAll('.sort-link').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        const field = link.dataset.field;
        if (sortField === field) {
          sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
          sortField = field;
          sortOrder = 'asc';
        }
        fetchUsers(currentPage);
      });
    });

    document.getElementById('tableBody').addEventListener('click', e => {
      const tr = e.target.closest('tr');
      if (!tr || !tr.dataset.user) return;
      const user = JSON.parse(tr.dataset.user);

      document.getElementById('userId').value = user.id;
      document.getElementById('nickname').value = user.nickname;
      document.getElementById('realname').value = user.realname || '';
      document.getElementById('phone').value = user.phone;
      document.getElementById('email').value = user.email;
      document.getElementById('role').value = user.role;
      document.getElementById('campus').value = user.campus;

      new bootstrap.Modal(document.getElementById('userModal')).show();
    });

    document.getElementById('deleteBtn').addEventListener('click', async () => {
        const id = document.getElementById('userId').value;
        if (!confirm('确定要删除此用户？')) return;
        const res = await fetch('api/delete_user.php', {
            method: 'POST',
            body: new URLSearchParams({ id })
        });
        const result = await res.json();
        if (result.success) {
            notyf.success('删除成功');
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            fetchOrders(currentPage);
        } else {
            notyf.error('删除失败：' + result.message);
        }
    });

    document.getElementById('userForm').addEventListener('submit', async e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      const res = await fetch('api/serUser_admin.php', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      if (result.success) {
        notyf.success('用户信息已成功更新');
        bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
        fetchUsers(currentPage);
        } else {
        notyf.error('更新失败：' + result.message);
      }
    });

    fetchUsers();
  </script>
  
</body>
</html>
