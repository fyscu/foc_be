// 技术员管理模块
class TechnicianManagement {
    constructor(app) {
        this.app = app;
        this.currentTechnicians = [];
    }

    // 初始化技术员管理页面
    async init() {
        console.log('初始化技术员管理页面...');
        // 等待DOM元素渲染完成后再初始化
        setTimeout(async () => {
            await this.loadTechnicians();
            this.bindEvents();
            this.addImportButton();
        }, 100);
    }
    
    // 添加导入按钮
    addImportButton() {
        const addBtn = document.getElementById('addTechnicianBtn');
        if (addBtn) {
            const importBtn = document.createElement('button');
            importBtn.id = 'importTechniciansBtn';
            importBtn.className = 'px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 ml-2';
            importBtn.innerHTML = '<i class="fas fa-file-import mr-2"></i>从用户导入';
            importBtn.addEventListener('click', () => {
                this.showImportTechniciansModal();
            });
            
            addBtn.parentNode.appendChild(importBtn);
        }
    }

    // 加载技术员列表
    async loadTechnicians() {
        try {
            const result = await API.getTechnicians();
            if (result.success) {
                this.currentTechnicians = result.data;
                this.renderTechnicianList(result.data);
                this.updateTechnicianStats(result.data);
            } else {
                this.app.showNotification('加载技术员列表失败', 'error');
            }
        } catch (error) {
            console.error('加载技术员列表失败:', error);
            this.app.showNotification('加载技术员列表失败', 'error');
        }
    }

    // 渲染技术员列表
    renderTechnicianList(technicians) {
        const container = document.getElementById('technicianList');
        if (!container) return;

        const html = technicians.map(tech => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                                <span class="text-white font-medium">${tech.name.charAt(0)}</span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${tech.name}</div>
                            <div class="text-sm text-gray-500">ID: ${tech.id}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                        <i class="fas fa-phone mr-2 text-gray-400"></i>
                        ${tech.phone || '未填写'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex items-center">
                        <i class="fas fa-tools mr-2 text-gray-400"></i>
                        ${tech.specialty || '通用维修'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        tech.status === 'online' ? 'bg-green-100 text-green-800' : 
                        'bg-gray-100 text-gray-800'
                    }">
                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full ${
                            tech.status === 'online' ? 'bg-green-400' : 
                            'bg-gray-400'
                        }"></span>
                        ${this.getStatusText(tech.status)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                        <span class="text-lg font-semibold ${tech.current_orders > 5 ? 'text-red-600' : tech.current_orders > 2 ? 'text-yellow-600' : 'text-green-600'}">
                            ${tech.current_orders || 0}
                        </span>
                        <span class="ml-1 text-gray-500">单</span>
                        ${tech.current_orders > 5 ? '<i class="fas fa-exclamation-triangle ml-2 text-red-500" title="工作量过重"></i>' : ''}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                        <button onclick="app.technicianMgmt.viewTechnicianDetails('${tech.id}')" 
                                class="text-blue-600 hover:text-blue-900" title="查看详情">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="app.technicianMgmt.editTechnician('${tech.id}')" 
                                class="text-green-600 hover:text-green-900" title="编辑">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="app.technicianMgmt.toggleTechnicianStatus('${tech.id}', '${tech.status}')" 
                                class="text-orange-600 hover:text-orange-900" title="切换状态">
                            <i class="fas fa-toggle-on"></i>
                        </button>
                        <button onclick="app.technicianMgmt.viewTechnicianOrders('${tech.id}')" 
                                class="text-purple-600 hover:text-purple-900" title="查看订单">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        container.innerHTML = html || '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">暂无技术员</td></tr>';
    }

    // 更新技术员统计
    updateTechnicianStats(technicians) {
        const onlineCount = technicians.filter(t => t.status === 'online').length;
        const offlineCount = technicians.filter(t => t.status === 'offline').length;
        const totalOrders = technicians.reduce((sum, t) => sum + (t.current_orders || 0), 0);

        // 更新页面统计显示
        const statsContainer = document.getElementById('technicianStats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-500 rounded-full">
                                <i class="fas fa-user-check text-white"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">在岗技术员</p>
                                <p class="text-2xl font-bold text-green-600">${onlineCount}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 bg-gray-500 rounded-full">
                                <i class="fas fa-user-times text-white"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-800">不在岗</p>
                                <p class="text-2xl font-bold text-gray-600">${offlineCount}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-500 rounded-full">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-800">总技术员</p>
                                <p class="text-2xl font-bold text-blue-600">${technicians.length}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-500 rounded-full">
                                <i class="fas fa-tasks text-white"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-purple-800">总订单数</p>
                                <p class="text-2xl font-bold text-purple-600">${totalOrders}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    // 绑定事件
    bindEvents() {
        // 添加技术员按钮
        const addBtn = document.getElementById('addTechnicianBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                this.showAddTechnicianModal();
            });
        }

        // 搜索功能
        const searchInput = document.getElementById('technicianSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterTechnicians(e.target.value);
            });
        }

        // 状态筛选
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.filterByStatus(e.target.value);
            });
        }
    }

    // 获取状态文本
    getStatusText(status) {
        const statusMap = {
            'online': '在岗',
            'offline': '不在岗'
        };
        return statusMap[status] || status;
    }

    // 显示添加技术员模态框
    showAddTechnicianModal() {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="addTechnicianModal">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">添加技术员</h3>
                        <form id="addTechnicianForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">姓名 *</label>
                                <input type="text" name="name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">联系电话</label>
                                <input type="tel" name="phone" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">专业领域</label>
                                <select name="specialty" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">请选择专业领域</option>
                                    <option value="硬件维修">硬件维修</option>
                                    <option value="软件维修">软件维修</option>
                                    <option value="网络维修">网络维修</option>
                                    <option value="数据恢复">数据恢复</option>
                                    <option value="通用维修">通用维修</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">初始状态</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="online">在岗</option>
                                    <option value="offline">不在岗</option>
                                </select>
                            </div>
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="document.getElementById('addTechnicianModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    添加技术员
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
        
        // 绑定表单提交事件
        document.getElementById('addTechnicianForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.addTechnician(e.target);
        });
    }

    // 添加技术员
    async addTechnician(form) {
        const formData = new FormData(form);
        const technicianData = Object.fromEntries(formData.entries());
        
        try {
            const result = await API.createTechnician(technicianData);
            if (result.success) {
                this.app.showNotification('技术员添加成功', 'success');
                document.getElementById('addTechnicianModal').remove();
                await this.loadTechnicians(); // 重新加载列表
            } else {
                this.app.showNotification('添加失败：' + result.message, 'error');
            }
        } catch (error) {
            console.error('添加技术员失败:', error);
            this.app.showNotification('添加技术员失败', 'error');
        }
    }

    // 查看技术员详情
    async viewTechnicianDetails(technicianId) {
        try {
            const result = await API.getTechnicianById(technicianId);
            if (result.success) {
                this.showTechnicianDetailsModal(result.data);
            }
        } catch (error) {
            console.error('获取技术员详情失败:', error);
            this.app.showNotification('获取技术员详情失败', 'error');
        }
    }

    // 显示技术员详情模态框
    showTechnicianDetailsModal(technician) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="technicianDetailsModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">技术员详情</h3>
                            <button onclick="document.getElementById('technicianDetailsModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h4 class="font-medium text-gray-700 border-b pb-2">基本信息</h4>
                                <div class="space-y-2">
                                    <p><strong>姓名：</strong>${technician.name}</p>
                                    <p><strong>ID：</strong>${technician.id}</p>
                                    <p><strong>联系电话：</strong>${technician.phone || '未填写'}</p>
                                    <p><strong>专业领域：</strong>${technician.specialty || '通用维修'}</p>
                                    <p><strong>当前状态：</strong>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            technician.status === 'online' ? 'bg-green-100 text-green-800' : 
                                            'bg-gray-100 text-gray-800'
                                        }">
                                            ${this.getStatusText(technician.status)}
                                        </span>
                                    </p>
                                    <p><strong>当前订单数：</strong>${technician.current_orders || 0} 单</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <h4 class="font-medium text-gray-700 border-b pb-2">统计信息</h4>
                                <div class="space-y-2">
                                    <p><strong>总完成订单：</strong>${technician.total_completed || 0} 单</p>
                                    <p><strong>平均完成时间：</strong>${technician.avg_completion_time || '暂无数据'}</p>
                                    <p><strong>客户满意度：</strong>${technician.satisfaction_rate || '暂无评价'}</p>
                                    <p><strong>加入时间：</strong>${technician.created_at || '未知'}</p>
                                    <p><strong>最后活动：</strong>${technician.last_active || '未知'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button onclick="app.technicianMgmt.editTechnician('${technician.id}')" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                编辑信息
                            </button>
                            <button onclick="app.technicianMgmt.viewTechnicianOrders('${technician.id}')" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                查看订单
                            </button>
                            <button onclick="document.getElementById('technicianDetailsModal').remove()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                关闭
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
    }

    // 编辑技术员
    async editTechnician(technicianId) {
        try {
            const result = await API.getTechnicianById(technicianId);
            if (result.success) {
                this.showEditTechnicianModal(result.data);
            }
        } catch (error) {
            console.error('获取技术员信息失败:', error);
            this.app.showNotification('获取技术员信息失败', 'error');
        }
    }

    // 显示编辑技术员模态框
    showEditTechnicianModal(technician) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="editTechnicianModal">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">编辑技术员</h3>
                        <form id="editTechnicianForm" class="space-y-4">
                            <input type="hidden" name="id" value="${technician.id}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">姓名 *</label>
                                <input type="text" name="name" value="${technician.name}" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">联系电话</label>
                                <input type="tel" name="phone" value="${technician.phone || ''}" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">专业领域</label>
                                <select name="specialty" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">请选择专业领域</option>
                                    <option value="硬件维修" ${technician.specialty === '硬件维修' ? 'selected' : ''}>硬件维修</option>
                                    <option value="软件维修" ${technician.specialty === '软件维修' ? 'selected' : ''}>软件维修</option>
                                    <option value="网络维修" ${technician.specialty === '网络维修' ? 'selected' : ''}>网络维修</option>
                                    <option value="数据恢复" ${technician.specialty === '数据恢复' ? 'selected' : ''}>数据恢复</option>
                                    <option value="通用维修" ${technician.specialty === '通用维修' ? 'selected' : ''}>通用维修</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">状态</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="online" ${technician.status === 'online' ? 'selected' : ''}>在岗</option>
                                    <option value="offline" ${technician.status === 'offline' ? 'selected' : ''}>不在岗</option>
                                </select>
                            </div>
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="document.getElementById('editTechnicianModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    保存更改
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
        
        // 绑定表单提交事件
        document.getElementById('editTechnicianForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.updateTechnician(e.target);
        });
    }

    // 更新技术员信息
    async updateTechnician(form) {
        const formData = new FormData(form);
        const technicianData = Object.fromEntries(formData.entries());
        
        try {
            const result = await API.updateTechnician(technicianData.id, technicianData);
            if (result.success) {
                this.app.showNotification('技术员信息更新成功', 'success');
                document.getElementById('editTechnicianModal').remove();
                await this.loadTechnicians(); // 重新加载列表
            } else {
                this.app.showNotification('更新失败：' + result.message, 'error');
            }
        } catch (error) {
            console.error('更新技术员信息失败:', error);
            this.app.showNotification('更新技术员信息失败', 'error');
        }
    }

    // 切换技术员状态
    async toggleTechnicianStatus(technicianId, currentStatus) {
        // 简化为二元状态切换：在岗 <-> 不在岗
        const nextStatus = currentStatus === 'online' ? 'offline' : 'online';
        
        try {
            const result = await API.updateTechnicianStatus(technicianId, nextStatus);
            if (result.success) {
                this.app.showNotification(`技术员状态已更新为：${this.getStatusText(nextStatus)}`, 'success');
                await this.loadTechnicians(); // 重新加载列表
            } else {
                this.app.showNotification('状态更新失败：' + result.message, 'error');
            }
        } catch (error) {
            console.error('更新技术员状态失败:', error);
            this.app.showNotification('更新技术员状态失败', 'error');
        }
    }

    // 查看技术员订单
    async viewTechnicianOrders(technicianId) {
        try {
            const result = await API.getTechnicianOrders(technicianId);
            if (result.success) {
                this.showTechnicianOrdersModal(technicianId, result.data);
            }
        } catch (error) {
            console.error('获取技术员订单失败:', error);
            this.app.showNotification('获取技术员订单失败', 'error');
        }
    }

    // 显示技术员订单模态框
    showTechnicianOrdersModal(technicianId, orders) {
        const technician = this.currentTechnicians.find(t => t.id == technicianId);
        const technicianName = technician ? technician.name : '未知技术员';
        
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="technicianOrdersModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">${technicianName} 的订单列表</h3>
                            <button onclick="document.getElementById('technicianOrdersModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">订单号</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">客户</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">设备</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">接单时间</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${orders.map(order => `
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                ${order.order_number}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>
                                                    <div class="font-medium">${order.customer_name}</div>
                                                    <div class="text-gray-400">${order.customer_phone}</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>
                                                    <div class="font-medium">${order.device_type}</div>
                                                    <div class="text-gray-400">${order.problem_description.substring(0, 30)}...</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    order.status === 'pending' ? 'bg-gray-100 text-gray-800' :
                                                    order.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                                    order.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                    order.status === 'ready_for_pickup' ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-gray-100 text-gray-800'
                                                }">
                                                    ${this.app.getStatusText(order.status)}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${order.assigned_at || '未知'}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="app.viewOrder('${order.id}')" 
                                                        class="text-blue-600 hover:text-blue-900 mr-2">
                                                    查看详情
                                                </button>
                                                ${order.status === 'in_progress' ? `
                                                    <button onclick="app.workflow.updateOrderStatus('${order.id}', 'ready_for_pickup')" 
                                                            class="text-green-600 hover:text-green-900">
                                                        完成维修
                                                    </button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button onclick="document.getElementById('technicianOrdersModal').remove()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                关闭
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
    }

    // 筛选技术员
    filterTechnicians(searchTerm) {
        const filteredTechnicians = this.currentTechnicians.filter(tech => 
            tech.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            tech.phone.includes(searchTerm) ||
            tech.specialty.toLowerCase().includes(searchTerm.toLowerCase())
        );
        this.renderTechnicianList(filteredTechnicians);
    }

    // 按状态筛选
    filterByStatus(status) {
        if (status === 'all') {
            this.renderTechnicianList(this.currentTechnicians);
        } else {
            const filteredTechnicians = this.currentTechnicians.filter(tech => tech.status === status);
            this.renderTechnicianList(filteredTechnicians);
        }
    }

    // 批量操作
    async batchUpdateStatus(technicianIds, newStatus) {
        try {
            const promises = technicianIds.map(id => API.updateTechnicianStatus(id, newStatus));
            const results = await Promise.all(promises);
            
            const successCount = results.filter(r => r.success).length;
            this.app.showNotification(`成功更新 ${successCount} 个技术员状态`, 'success');
            
            await this.loadTechnicians(); // 重新加载列表
        } catch (error) {
            console.error('批量更新状态失败:', error);
            this.app.showNotification('批量更新状态失败', 'error');
        }
    }

    // 导出技术员数据
    exportTechnicianData() {
        const csvContent = this.generateCSV(this.currentTechnicians);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `technicians_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // 显示导入技术员模态框
    async showImportTechniciansModal() {
        try {
            // 获取技术员用户列表
            const response = await fetch('api/get_technician_users.php');
            const result = await response.json();
            
            if (!result.success) {
                this.app.showNotification('获取技术员用户列表失败', 'error');
                return;
            }
            
            const users = result.data || [];
            
            if (users.length === 0) {
                this.app.showNotification('没有找到可导入的技术员用户', 'warning');
                return;
            }
            
            const modalHtml = `
                <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="importTechniciansModal">
                    <div class="relative top-20 mx-auto p-5 border w-3/4 max-w-2xl shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">从用户导入技术员</h3>
                                <button onclick="document.getElementById('importTechniciansModal').remove()" 
                                        class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-4">
                                系统找到了 ${users.length} 个角色为技术员的用户，可以一键导入到技术员管理系统中。
                            </p>
                            
                            <div class="max-h-60 overflow-y-auto mb-4">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                <input type="checkbox" id="selectAllUsers" class="form-checkbox">
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">用户ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">昵称</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">手机号</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${users.map(user => `
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input type="checkbox" class="form-checkbox user-checkbox" value="${user.id}" 
                                                           data-nickname="${user.nickname}" data-phone="${user.phone || ''}">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.id}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${user.nickname}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.phone || '未设置'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="document.getElementById('importTechniciansModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button type="button" id="importSelectedBtn"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    导入选中用户
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('modalContainer').innerHTML = modalHtml;
            
            // 绑定全选事件
            document.getElementById('selectAllUsers').addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
            });
            
            // 绑定导入按钮事件
            document.getElementById('importSelectedBtn').addEventListener('click', () => {
                this.importSelectedTechnicians();
            });
            
        } catch (error) {
            console.error('显示导入技术员模态框失败:', error);
            this.app.showNotification('显示导入技术员模态框失败', 'error');
        }
    }
    
    // 导入选中的技术员
    async importSelectedTechnicians() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        
        if (checkboxes.length === 0) {
            this.app.showNotification('请至少选择一个用户进行导入', 'warning');
            return;
        }
        
        const selectedUsers = Array.from(checkboxes).map(checkbox => ({
            id: checkbox.value,
            nickname: checkbox.getAttribute('data-nickname'),
            phone: checkbox.getAttribute('data-phone')
        }));
        
        try {
            const response = await fetch('api/import_technicians.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ users: selectedUsers })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.app.showNotification(`成功导入 ${result.data.imported_count} 名技术员`, 'success');
                document.getElementById('importTechniciansModal').remove();
                await this.loadTechnicians(); // 重新加载列表
            } else {
                this.app.showNotification('导入失败：' + result.message, 'error');
            }
        } catch (error) {
            console.error('导入技术员失败:', error);
            this.app.showNotification('导入技术员失败', 'error');
        }
    }

    // 生成CSV内容
    generateCSV(technicians) {
        const headers = ['ID', '姓名', '联系电话', '专业领域', '状态', '当前订单数', '总完成订单'];
        const rows = technicians.map(tech => [
            tech.id,
            tech.name,
            tech.phone || '',
            tech.specialty || '',
            this.getStatusText(tech.status),
            tech.current_orders || 0,
            tech.total_completed || 0
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }

    // 刷新数据
    async refresh() {
        await this.loadTechnicians();
        this.app.showNotification('技术员数据已刷新', 'success');
    }
}
