<?php
session_start();
// --- 配置区域 ---
$ADMIN_PASSWORD = '我不告诉你'; 
// ----------------
$isMasterView = isset($_GET['token']) && !empty($_GET['token']);
$isAdminLoggedIn = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        header("Location: index.php"); exit;
    } else { $loginError = "密码错误"; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>师徒系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <style>
        :root { --primary: #3370ff; --bg: #f5f6f7; --white: #ffffff; --text-main: #1f2329; --text-sub: #646a73; --danger: #f54a45; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: var(--bg); color: var(--text-main); }
        .container { max-width: 1000px; margin: 0 auto; min-height: 100vh; padding: 30px 20px; }
        .card-box { background: var(--white); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 24px; }
        h2 { font-size: 1.3rem; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; margin-top: 0; }
        h3 { margin-top: 0; margin-bottom: 10px; font-size: 16px; }
        button { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.2s; }
        button:hover { opacity: 0.9; } button.secondary { background: #eff0f1; color: var(--text-main); } button.danger { background: var(--danger); }
        button.btn-sm { padding: 6px 12px; font-size: 12px; }
        textarea, input[type="password"] { width: 100%; border: 1px solid #dee0e3; border-radius: 6px; padding: 12px; font-size: 14px; margin-bottom: 10px; outline: none; }
        textarea:focus { border-color: var(--primary); }
        .login-wrapper { display: flex; justify-content: center; align-items: center; height: 80vh; }
        .login-box { text-align: center; width: 100%; max-width: 360px; }
        
        /* 师傅列表网格 */
        .appr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; }
        .appr-card { background: var(--white); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; border: 1px solid #eef0f4; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .appr-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .appr-name { font-size: 18px; font-weight: 600; }
        .appr-time { font-size: 13px; color: #8f959e; margin-bottom: 15px; }
        .appr-status { padding: 4px 10px; border-radius: 20px; font-size: 12px; }
        .st-0 { background: #fff7e6; color: #d48806; } .st-1 { background: #e6fffb; color: #00b67a; } .st-2 { background: #fff1f0; color: #f54a45; }
        .appr-action { margin-top: auto; text-align: right; border-top: 1px solid #f5f6f7; padding-top: 15px; }
        
        /* 弹窗样式 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 999; justify-content: center; align-items: center; }
        .modal-active { display: flex; }
        .modal-body { background: var(--white); width: 90%; max-width: 480px; border-radius: 16px; padding: 28px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); max-height: 80vh; overflow-y:auto; }
        .msg-box { background: #f8f9fa; padding: 16px; border-radius: 8px; font-size: 15px; margin: 15px 0 25px 0; max-height: 300px; overflow-y: auto; border: 1px solid #eee; }
        .modal-footer { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;} .modal-footer button { flex: 1; padding: 12px; }
        
        /* 管理员表格优化 */
        .admin-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .admin-table th, .admin-table td { padding: 12px 10px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        .action-group { display: flex; gap: 8px; }
        
        /* 可点击的师傅名字 */
        .clickable-name { color: var(--primary); font-weight: 600; cursor: pointer; text-decoration: none; border-bottom: 1px dashed var(--primary); }
        .clickable-name:hover { border-bottom-style: solid; opacity: 0.8; }
        
        /* 弹窗内的简单表格 */
        .mini-table { width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 13px; }
        .mini-table th { background: #f5f6f7; text-align: left; padding: 8px; color: #666; }
        .mini-table td { border-bottom: 1px solid #eee; padding: 8px; }
    </style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<div class="container">
    <?php if (!$isMasterView && !$isAdminLoggedIn): ?>
    <div class="login-wrapper"><div class="card-box login-box"><h2>系统登录</h2><form method="POST"><input type="password" name="login_pass" placeholder="管理员密码" required><button type="submit" style="width:100%">登录</button><?php if(isset($loginError)) echo "<p style='color:red;font-size:12px;margin-top:10px'>$loginError</p>"; ?></form></div></div>
    <?php endif; ?>

    <?php if (!$isMasterView && $isAdminLoggedIn): ?>
    <div id="admin-panel">
        <div class="card-box">
            <h2>数据导入</h2>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div><h3>1. 师傅数据</h3><textarea id="master-input" placeholder="粘贴：姓名 [Tab] 身份" style="height:100px;"></textarea><button onclick="importData('import_masters', 'master-input')">上传师傅</button></div>
                <div><h3>2. 师徒关系</h3><textarea id="appr-input" placeholder="粘贴：师傅 [Tab] 确认 [Tab] 徒弟 [Tab] 留言" style="height:100px;"></textarea><button onclick="importData('import_relationships', 'appr-input')">上传关系</button></div>
            </div>
        </div>
        <div class="card-box">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;"><h2>链接分发</h2><button class="secondary" onclick="loadMasterLinks()" style="padding:6px 12px; font-size:13px;">刷新</button></div>
            <div style="overflow-x: auto;"><table class="admin-table" id="link-table"><thead><tr><th>师傅姓名</th><th>徒弟数</th><th>身份</th><th>操作</th></tr></thead><tbody><tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">点击刷新加载</td></tr></tbody></table></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isMasterView): ?>
    <div id="master-panel">
        <div class="card-box" style="text-align: center; border-left: 5px solid var(--primary);"><h2 style="border:none; margin-bottom: 8px;" id="master-greeting">👋 您好</h2><p style="color:var(--text-sub); font-size:14px;">请查阅下方的申请并确认。</p></div>
        <div id="appr-list" class="appr-grid"></div>
        <div id="empty-state" style="display:none; text-align:center; padding: 60px; color:#aaa;">📭 暂无新的申请</div>
    </div>
    <?php endif; ?>
</div>

<div id="detail-modal" class="modal-overlay"><div class="modal-body"><h3>申请详情</h3><p style="color:var(--text-sub); font-size:14px; margin:0 0 20px 0;">来自：<span id="m-name" style="color:#000; font-weight:bold; font-size:16px;"></span></p><div class="msg-box" id="m-msg"></div><input type="hidden" id="m-id"><div class="modal-footer"><button class="secondary" onclick="closeModal('detail-modal')">取消</button><button class="danger" onclick="submitApprove(2)">婉拒</button><button onclick="submitApprove(1)">确认收徒</button></div></div></div>

<div id="admin-view-modal" class="modal-overlay">
    <div class="modal-body" style="max-width: 600px;">
        <h3>徒弟列表</h3>
        <p style="color:var(--text-sub); margin-bottom:15px;">师傅：<span id="admin-v-master" style="font-weight:bold;color:#333;"></span></p>
        <div id="admin-v-content" style="max-height: 400px; overflow-y: auto;">
            <table class="mini-table">
                <thead><tr><th>姓名</th><th>留言</th><th>状态</th></tr></thead>
                <tbody id="admin-v-tbody"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button class="secondary" onclick="closeModal('admin-view-modal')">关闭</button>
        </div>
    </div>
</div>

<script>
    const API_URL = 'api.php';
    const notyf = new Notyf({ duration: 3000, position: { x: 'center', y: 'top' } });
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    window.addEventListener('DOMContentLoaded', () => {
        if (token) loadMasterView(token);
        else if(document.getElementById('link-table')) loadMasterLinks();
    });

    async function importData(action, inputId) {
        const input = document.getElementById(inputId);
        if(!input.value.trim()) return notyf.error('内容不能为空');
        const formData = new FormData();
        formData.append('action', action);
        formData.append('data', input.value);
        try {
            const res = await fetch(API_URL, { method: 'POST', body: formData }).then(r => r.json());
            if(res.status === 'success') {
                notyf.success(res.message);
                input.value = '';
                if(res.failed_details && res.failed_details.length > 0) {
                    alert("以下数据导入失败，请检查格式：\n\n" + res.failed_details.join("\n"));
                }
            } else { notyf.error(res.message); }
        } catch (e) { notyf.error('网络请求错误'); }
    }

    async function loadMasterLinks() {
        const formData = new FormData();
        formData.append('action', 'get_master_list');
        const res = await fetch(API_URL, { method: 'POST', body: formData }).then(r => r.json());
        const tbody = document.querySelector('#link-table tbody');
        tbody.innerHTML = '';
        const baseUrl = window.location.href.split('?')[0];
        
        if(!res.data || res.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999">暂无有徒弟的师傅数据</td></tr>';
            return;
        }

        res.data.forEach(item => {
            const link = `${baseUrl}?token=${item.token}`;
            // 姓名加上点击事件，调用 adminViewApprentices
            const tr = `<tr>
                <td><a class="clickable-name" onclick="adminViewApprentices('${item.token}')">${item.masterName}</a></td>
                <td style="text-align:center"><span style="background:#f0f1f3;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">${item.appr_count}</span></td>
                <td><span style="font-size:12px;background:#e8f3ff;padding:2px 6px;border-radius:4px;color:#3370ff">${item.type}</span></td>
                <td><div class="action-group"><button class="btn-sm" onclick="copyInvite('${item.masterName}', '${link}')">📋 复制邀请</button><button class="btn-sm secondary" onclick="copyLink('${link}')">仅复制链接</button></div></td>
            </tr>`;
            tbody.innerHTML += tr;
        });
    }

    // --- 管理员点击查看徒弟详情 ---
    async function adminViewApprentices(token) {
        const formData = new FormData();
        formData.append('action', 'get_master_view'); // 复用获取师傅视图的接口
        formData.append('token', token);
        
        try {
            const res = await fetch(API_URL, { method: 'POST', body: formData }).then(r => r.json());
            if(res.status === 'success') {
                document.getElementById('admin-v-master').innerText = res.master.masterName;
                const tbody = document.getElementById('admin-v-tbody');
                tbody.innerHTML = '';
                
                res.apprentices.forEach(app => {
                    let status = '<span style="color:#d48806">待确认</span>';
                    if(app.ifApproved == 1) status = '<span style="color:#00b67a;font-weight:bold">已收徒</span>';
                    if(app.ifApproved == 2) status = '<span style="color:#f54a45">已婉拒</span>';
                    
                    // 截取过长留言
                    let msg = app.message || '-';
                    if(msg.length > 20) msg = msg.substring(0, 20) + '...';
                    
                    tbody.innerHTML += `<tr>
                        <td>${app.apprenticeName}</td>
                        <td title="${app.message}">${msg}</td>
                        <td>${status}</td>
                    </tr>`;
                });
                
                const modal = document.getElementById('admin-view-modal');
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('modal-active'), 10);
            } else {
                notyf.error(res.message);
            }
        } catch(e) { notyf.error('加载详情失败'); }
    }

    function copyInvite(name, link) {
        const msg = `${name}师傅，恭喜您有徒弟拜师。请点击下面的链接确认您的收徒信息。\n链接：${link}`;
        navigator.clipboard.writeText(msg).then(() => notyf.success('邀请语已复制'));
    }
    function copyLink(text) { navigator.clipboard.writeText(text).then(() => notyf.success('链接已复制')); }

    // --- 师傅端逻辑 ---
    async function loadMasterView(token) {
        const formData = new FormData();
        formData.append('action', 'get_master_view');
        formData.append('token', token);
        const res = await fetch(API_URL, { method: 'POST', body: formData }).then(r => r.json());
        if(res.status === 'error') { document.body.innerHTML = `<div class="container" style="display:flex;justify-content:center;align-items:center;height:100vh"><h3 style="color:red">${res.message}</h3></div>`; return; }
        document.getElementById('master-greeting').innerText = `👋 您好，${res.master.masterName} 师傅`;
        const list = document.getElementById('appr-list');
        list.innerHTML = '';
        if(res.apprentices.length === 0) { document.getElementById('empty-state').style.display = 'block'; return; }
        res.apprentices.forEach(item => {
            let st = '<span class="appr-status st-0">待确认</span>', btn = `<button onclick="openModal('${encodeURIComponent(JSON.stringify(item))}')">查看详情</button>`;
            if(item.ifApproved == 1) { st = '<span class="appr-status st-1">已收徒</span>'; btn = `<button class="secondary" disabled>已同意</button>`; }
            if(item.ifApproved == 2) { st = '<span class="appr-status st-2">已婉拒</span>'; btn = `<button class="secondary" disabled>已拒绝</button>`; }
            list.innerHTML += `<div class="appr-card"><div class="appr-header"><div class="appr-name">${item.apprenticeName}</div>${st}</div><div class="appr-time">提交时间：${item.updateTime}</div><div style="flex:1"></div><div class="appr-action">${btn}</div></div>`;
        });
    }

    // --- 弹窗通用 ---
    function openModal(itemStr) {
        const item = JSON.parse(decodeURIComponent(itemStr));
        document.getElementById('m-name').innerText = item.apprenticeName;
        document.getElementById('m-msg').innerText = item.message || "（无留言）";
        document.getElementById('m-id').value = item.id;
        const modal = document.getElementById('detail-modal');
        modal.style.display = 'flex'; setTimeout(() => modal.classList.add('modal-active'), 10);
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('modal-active'); setTimeout(() => modal.style.display = 'none', 300);
    }
    async function submitApprove(status) {
        const formData = new FormData();
        formData.append('action', 'approve_apprentice');
        formData.append('appr_id', document.getElementById('m-id').value);
        formData.append('status', status);
        await fetch(API_URL, { method: 'POST', body: formData });
        notyf.success('操作成功'); closeModal('detail-modal'); setTimeout(() => location.reload(), 800);
    }
</script>
</body>
</html>