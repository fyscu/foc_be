// 技术员大屏功能
class TechnicianScreen {
    constructor(app) {
        this.app = app;
    }

    // 初始化技术员大屏页面
    init() {
        console.log('初始化技术员大屏页面...');
        
        // 加载订单数据
        this.loadOrders();
        
        // 设置自动刷新
        this.setupAutoRefresh();
        
        // 加载QR码生成库
        this.loadQRCodeLibrary();
    }

    // 加载订单数据
    async loadOrders() {
        try {
            // 获取待接单的订单
            const pendingResult = await API.getOrders({
                status: 'pending'
            });
            
            // 获取维修中的订单
            const processingResult = await API.getOrders({
                status: 'processing'
            });
            
            if (pendingResult.success && processingResult.success) {
                const pendingOrders = pendingResult.data || [];
                const processingOrders = processingResult.data || [];
                
                // 合并订单数据
                const allOrders = [...pendingOrders, ...processingOrders];
                
                // 更新统计数据
                this.updateStats({
                    pending: pendingOrders.length,
                    processing: processingOrders.length
                });
                
                // 渲染订单列表
                this.renderOrderList(allOrders);
                
                // 单独渲染已分配订单列表
                this.renderAssignedOrdersList(processingOrders);
            } else {
                console.error('加载订单失败:', pendingResult.message || processingResult.message);
            }
        } catch (error) {
            console.error('加载订单出错:', error);
        }
    }

    // 更新统计数据
    updateStats(orders) {
        const pendingOrders = orders.filter(order => order.status === 'pending');
        const processingOrders = orders.filter(order => order.status === 'processing');
        
        document.getElementById('techPendingCount').textContent = pendingOrders.length;
        document.getElementById('techProcessingCount').textContent = processingOrders.length;
        
        // 获取在线技术员数量
        this.getOnlineTechnicians();
    }

    // 获取在线技术员数量
    async getOnlineTechnicians() {
        try {
            const result = await API.getTechnicians({ status: 'online' });
            if (result.success) {
                document.getElementById('onlineTechCount').textContent = result.data.length;
            }
        } catch (error) {
            console.error('获取技术员数据失败:', error);
        }
    }

    // 渲染订单列表
    renderOrderList(orders) {
        const orderListElement = document.getElementById('techOrderList');
        if (!orderListElement) return;
        
        // 清空现有内容
        orderListElement.innerHTML = '';
        
        if (orders.length === 0) {
            orderListElement.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        暂无订单数据
                    </td>
                </tr>
            `;
            return;
        }
        
        // 按状态和创建时间排序
        orders.sort((a, b) => {
            // 首先按状态排序：pending 在前，processing 在后
            if (a.status !== b.status) {
                return a.status === 'pending' ? -1 : 1;
            }
            // 然后按创建时间排序：早的在前
            return new Date(a.created_at) - new Date(b.created_at);
        });
        
        // 生成订单行
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.className = order.status === 'pending' ? 'bg-yellow-50' : '';
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${order.order_number}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.customer_name}</div>
                    <div class="text-xs text-gray-500">${order.customer_phone}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${order.device_type}</div>
                    <div class="text-xs text-gray-500">${order.device_model || '未指定'}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 truncate max-w-xs" title="${order.problem_description}">
                        ${order.problem_description}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${this.getStatusBadge(order.status)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${order.technician_name ? `
                        <div class="text-sm text-gray-900">${order.technician_name}</div>
                        <div class="text-xs text-gray-500">${order.technician_phone || ''}</div>
                    ` : '未分配'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    ${this.getActionButtons(order)}
                </td>
            `;
            
            orderListElement.appendChild(row);
        });
        
        // 渲染已分配订单列表（维修中的订单）
        this.renderAssignedOrdersList(orders.filter(order => order.status === 'processing'));
    }
    
    // 渲染已分配订单列表
    renderAssignedOrdersList(assignedOrders) {
        const assignedOrdersContainer = document.getElementById('assignedOrdersList');
        if (!assignedOrdersContainer) return;
        
        // 清空现有内容
        assignedOrdersContainer.innerHTML = '';
        
        if (!assignedOrders || assignedOrders.length === 0) {
            assignedOrdersContainer.innerHTML = `
                <div class="text-center text-gray-500 py-4">
                    暂无已分配的订单
                </div>
            `;
            return;
        }
        
        console.log('已分配订单数据:', assignedOrders);
        
        // 创建已分配订单列表
        const orderCards = document.createElement('div');
        orderCards.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
        
        assignedOrders.forEach(order => {
            const card = document.createElement('div');
            card.className = 'bg-white p-4 rounded-lg shadow';
            
            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h4 class="font-medium text-gray-900">${order.order_number}</h4>
                        <p class="text-sm text-gray-600">${order.customer_name} - ${order.customer_phone}</p>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                        维修中
                    </span>
                </div>
                <p class="text-sm text-gray-700 mb-3 truncate" title="${order.problem_description}">
                    ${order.problem_description}
                </p>
                <div class="text-sm text-gray-600 mb-3">
                    <p>设备: ${order.device_type} ${order.device_model || ''}</p>
                    <p>技术员: <strong>${order.technician_name || '未分配'}</strong></p>
                </div>
                <button class="w-full px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                        onclick="app.technicianScreen.showQRCode(${order.id})">
                    <i class="fas fa-qrcode mr-2"></i>显示维修记录二维码
                </button>
            `;
            
            orderCards.appendChild(card);
        });
        
        assignedOrdersContainer.appendChild(orderCards);
    }

    // 获取状态标签
    getStatusBadge(status) {
        const statusMap = {
            'pending': { text: '待接单', class: 'bg-yellow-100 text-yellow-800' },
            'processing': { text: '维修中', class: 'bg-blue-100 text-blue-800' },
            'ready': { text: '待取机', class: 'bg-green-100 text-green-800' },
            'completed': { text: '已完成', class: 'bg-gray-100 text-gray-800' }
        };
        
        const statusInfo = statusMap[status] || { text: '未知', class: 'bg-gray-100 text-gray-800' };
        
        return `
            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusInfo.class}">
                ${statusInfo.text}
            </span>
        `;
    }

    // 获取操作按钮
    getActionButtons(order) {
        if (order.status === 'pending') {
            return `
                <button class="text-blue-600 hover:text-blue-900 mr-3" onclick="app.technicianScreen.assignTechnician(${order.id})">
                    接单
                </button>
            `;
        } else if (order.status === 'processing') {
            return `
                <button class="text-green-600 hover:text-green-900 mr-3" onclick="app.technicianScreen.showQRCode(${order.id})">
                    维修记录
                </button>
                <button class="text-orange-600 hover:text-orange-900" onclick="app.technicianScreen.markAsReady(${order.id})">
                    完成维修
                </button>
            `;
        }
        return '';
    }

    // 分配技术员
    async assignTechnician(orderId) {
        try {
            // 获取技术员列表
            const techResult = await API.getTechnicians({ status: 'online' });
            if (!techResult.success || techResult.data.length === 0) {
                this.app.showNotification('没有在线技术员可分配', 'error');
                return;
            }
            
            // 显示技术员选择对话框
            const technicians = techResult.data;
            const techOptions = technicians.map(tech => `
                <option value="${tech.id}">${tech.name} (${tech.specialty || '通用'})</option>
            `).join('');
            
            const dialog = document.createElement('div');
            dialog.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
            dialog.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">分配技术员</h3>
                    <select id="technicianSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md mb-4">
                        ${techOptions}
                    </select>
                    <div class="flex justify-end space-x-3">
                        <button id="cancelAssign" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700">
                            取消
                        </button>
                        <button id="confirmAssign" class="px-4 py-2 bg-blue-600 text-white rounded-md">
                            确认分配
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            // 绑定事件
            document.getElementById('cancelAssign').addEventListener('click', () => {
                document.body.removeChild(dialog);
            });
            
            document.getElementById('confirmAssign').addEventListener('click', async () => {
                const techId = document.getElementById('technicianSelect').value;
                document.body.removeChild(dialog);
                
                // 调用API分配技术员
                const result = await API.assignTechnician(orderId, techId);
                if (result.success) {
                    this.app.showNotification('技术员分配成功', 'success');
                    this.loadOrders(); // 重新加载订单列表
                } else {
                    this.app.showNotification('技术员分配失败: ' + result.message, 'error');
                }
            });
            
        } catch (error) {
            console.error('分配技术员失败:', error);
            this.app.showNotification('分配技术员失败，请重试', 'error');
        }
    }

    // 标记为待取机
    async markAsReady(orderId) {
        try {
            const result = await API.updateOrderStatus(orderId, 'ready');
            if (result.success) {
                this.app.showNotification('订单已标记为待取机', 'success');
                this.loadOrders(); // 重新加载订单列表
            } else {
                this.app.showNotification('更新订单状态失败: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('更新订单状态失败:', error);
            this.app.showNotification('更新订单状态失败，请重试', 'error');
        }
    }

    // 加载QR码生成库
    loadQRCodeLibrary() {
        if (window.QRCode) return; // 如果已加载则跳过
        
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js';
        document.head.appendChild(script);
    }

    // 生成QR码
    generateQRCode(orderId) {
        // 确保QR码库已加载
        if (!window.QRCode) {
            setTimeout(() => this.generateQRCode(orderId), 500);
            return;
        }
        
        // 生成技术员维修记录页面的URL
        const repairUrl = `${window.location.origin}/technician_repair.php?id=${orderId}`;
        
        // 检查QR码容器是否存在
        const qrContainer = document.getElementById('technicianQRCode');
        if (!qrContainer) return;
        
        // 清空容器
        qrContainer.innerHTML = '';
        
        // 生成QR码
        new QRCode(qrContainer, {
            text: repairUrl,
            width: 128,
            height: 128,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // 显示URL
        const urlDisplay = document.createElement('div');
        urlDisplay.className = 'text-xs text-gray-500 mt-2 text-center';
        urlDisplay.textContent = repairUrl;
        qrContainer.parentNode.appendChild(urlDisplay);
    }

    // 显示特定订单的QR码
    showQRCode(orderId) {
        // 创建对话框
        const dialog = document.createElement('div');
        dialog.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
        dialog.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-medium text-gray-900 mb-4">技术员维修记录二维码</h3>
                <p class="text-sm text-gray-600 mb-4">
                    请技术员扫描以下二维码，进入维修记录页面填写维修信息。
                </p>
                <div class="flex justify-center mb-4">
                    <div id="orderQRCode" class="border p-4 bg-white"></div>
                </div>
                <div class="flex justify-end">
                    <button id="closeQRDialog" class="px-4 py-2 bg-blue-600 text-white rounded-md">
                        关闭
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        
        // 生成QR码
        setTimeout(() => {
            const repairUrl = `${window.location.origin}/technician_repair.php?id=${orderId}`;
            new QRCode(document.getElementById('orderQRCode'), {
                text: repairUrl,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        }, 100);
        
        // 绑定关闭事件
        document.getElementById('closeQRDialog').addEventListener('click', () => {
            document.body.removeChild(dialog);
        });
    }

    // 设置自动刷新
    setupAutoRefresh() {
        // 每60秒刷新一次数据
        setInterval(() => this.loadOrders(), 60000);
    }
}