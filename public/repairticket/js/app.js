// 飞扬俱乐部电脑维修管理系统 - 主应用程序
class App {
    constructor() {
        this.currentPage = 'dashboard';
        this.currentActivity = null;
        this.orders = [];
        this.activities = [];
        
        // 初始化子模块
        this.orderEntry = new OrderEntry(this);
        this.workflow = new WorkflowManager(this);
        this.screenDisplay = new ScreenDisplay(this);
        this.technicianMgmt = new TechnicianManagement(this);
        this.smsNotification = new SMSNotification(this);
    }

    // 初始化应用
    async init() {
        console.log('初始化飞扬俱乐部电脑维修管理系统...');
        
        try {
            // 初始化子模块
            await this.smsNotification.init();
            
            // 绑定事件
            this.bindEvents();
            
            // 加载默认页面
            this.loadPage('dashboard');
            
            // 更新仪表板
            await this.updateDashboard();
            
            // 设置定时刷新
            setInterval(() => {
                if (this.currentPage === 'dashboard') {
                    this.updateDashboard();
                }
            }, 30000);
            
            console.log('系统初始化完成');
        } catch (error) {
            console.error('系统初始化失败:', error);
            this.showNotification('系统初始化失败', 'error');
        }
    }

    // 绑定事件
    bindEvents() {
        // 导航菜单事件
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-page]')) {
                e.preventDefault();
                const page = e.target.getAttribute('data-page');
                this.loadPage(page);
            }
        });

        // 活动选择事件
        const activitySelect = document.getElementById('activitySelect');
        if (activitySelect) {
            activitySelect.addEventListener('change', (e) => {
                this.currentActivity = e.target.value;
                this.updateDashboard();
            });
        }
    }

    // 加载页面
    async loadPage(pageName) {
        console.log(`加载页面: ${pageName}`);
        
        try {
            this.currentPage = pageName;
            
            // 更新导航状态
            this.updateNavigation(pageName);
            
            // 获取页面内容
            const content = await PageManager.getPageContent(pageName);
            
            // 更新页面内容
            const pageContent = document.getElementById('pageContent');
            if (pageContent) {
                pageContent.innerHTML = content;
            }
            
            // 绑定页面特定事件
            await this.bindPageEvents(pageName);
            
            console.log(`页面 ${pageName} 加载完成`);
        } catch (error) {
            console.error(`加载页面 ${pageName} 失败:`, error);
            this.showNotification('页面加载失败', 'error');
        }
    }

    // 绑定页面特定事件
    async bindPageEvents(pageName) {
        // 延迟执行，确保DOM元素完全渲染
        setTimeout(async () => {
            try {
                switch (pageName) {
                    case 'order-entry':
                        await this.orderEntry.init();
                        break;
                    case 'order-management':
                        await this.loadOrders();
                        break;
                    case 'technician-screen':
                        await this.screenDisplay.initTechnicianScreen();
                        break;
                    case 'service-screen':
                        await this.screenDisplay.initServiceScreen();
                        break;
                    case 'technician-management':
                        await this.technicianMgmt.init();
                        break;
                    case 'activity-management':
                        await this.initActivityManagement();
                        break;
                    case 'dashboard':
                        await this.updateDashboard();
                        break;
                }
            } catch (error) {
                console.error(`页面 ${pageName} 事件绑定失败:`, error);
            }
        }, 200);
    }

    // 更新导航状态
    updateNavigation(activePage) {
        document.querySelectorAll('[data-page]').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('data-page') === activePage) {
                link.classList.add('active');
            }
        });
    }


    // 更新仪表板
    async updateDashboard() {
        try {
            // 加载活动列表
            await this.loadActivities();
            
            // 加载订单统计
            const stats = await API.getOrderStats();
            if (stats && stats.success && stats.data) {
                this.updateDashboardStats(stats.data);
            } else {
                console.warn('获取订单统计失败:', stats);
            }
            
            // 加载最近订单
            console.log('开始加载最近订单...');
            const orders = await API.getOrders({ limit: 10, type: 'current' });
            console.log('仪表板API返回结果:', orders);
            
            if (orders && orders.success && orders.data) {
                // API返回的数据结构是 {orders: [...], total: ...}
                const orderList = orders.data.orders || orders.data || [];
                console.log('仪表板解析的订单数据:', orderList);
                console.log('仪表板订单数量:', orderList.length);
                this.updateRecentOrders(orderList);
            } else {
                console.warn('获取最近订单失败:', orders);
                this.updateRecentOrders([]);
            }
            
        } catch (error) {
            console.error('更新仪表板失败:', error);
            this.showNotification('仪表板数据加载失败', 'error');
        }
    }

    // 加载活动列表
    async loadActivities() {
        try {
            const result = await API.getActivities();
            if (result.success) {
                this.activities = result.data;
                this.updateActivitySelect();
                
                // 设置当前活动
                if (!this.currentActivity && this.activities.length > 0) {
                    this.currentActivity = this.activities[0].id;
                }
            }
        } catch (error) {
            console.error('加载活动列表失败:', error);
        }
    }

    // 更新活动选择器
    updateActivitySelect() {
        const select = document.getElementById('activitySelect');
        if (select && this.activities.length > 0) {
            select.innerHTML = this.activities.map(activity => 
                `<option value="${activity.id}" ${activity.id == this.currentActivity ? 'selected' : ''}>
                    ${activity.name}
                </option>`
            ).join('');
        }
    }

    // 更新仪表板统计
    updateDashboardStats(stats) {
        const elements = {
            'totalOrders': stats.total || 0,
            'pendingOrders': stats.pending || 0,
            'processingOrders': stats.in_progress || 0,
            'completedOrders': stats.completed || 0,
            'onlineTechnicians': stats.online_technicians || 0,
            'currentActivity': stats.currentActivity || '暂无活动',
            'activityDate': stats.activityDate || '暂无活动'
        };

        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
            }
        });
    }

    // 更新最近订单
    updateRecentOrders(orders) {
        const container = document.getElementById('recentOrdersList');
        if (!container) return;

        // 确保orders是数组
        if (!Array.isArray(orders)) {
            console.warn('订单数据不是数组格式:', orders);
            container.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">数据格式错误</td></tr>';
            return;
        }

        if (orders.length === 0) {
            container.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">暂无订单</td></tr>';
            return;
        }

        const html = orders.map(order => `
            <div class="bg-white p-4 rounded-lg shadow border-l-4 ${this.getStatusColor(order.status)}">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="font-medium text-gray-900">${order.order_number}</h4>
                        <p class="text-sm text-gray-600">${order.customer_name} - ${order.device_type}</p>
                        <p class="text-xs text-gray-500 mt-1">${order.created_at}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusBadgeClass(order.status)}">
                        ${this.getStatusText(order.status)}
                    </span>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    // 加载订单列表
    async loadOrders() {
        try {
            console.log('开始加载订单列表...');
            const result = await API.getOrders();
            console.log('API返回结果:', result);
            
            if (result && result.success) {
                // API返回的数据结构是 {orders: [...], total: ...}
                const orders = result.data.orders || result.data || [];
                console.log('解析的订单数据:', orders);
                console.log('订单数量:', orders.length);
                
                this.orders = orders;
                this.renderOrdersList(orders);
            } else {
                console.error('API返回失败:', result);
                this.showNotification('加载订单失败: ' + (result?.message || '未知错误'), 'error');
            }
        } catch (error) {
            console.error('加载订单失败:', error);
            this.showNotification('加载订单失败: ' + error.message, 'error');
        }
    }

    // 渲染订单列表
    renderOrdersList(orders) {
        console.log('开始渲染订单列表，订单数据:', orders);
        
        const container = document.getElementById('orderList');
        if (!container) {
            console.error('找不到订单列表容器 #orderList');
            return;
        }

        console.log('找到订单列表容器，开始渲染...');

        if (!Array.isArray(orders) || orders.length === 0) {
            console.log('订单列表为空或不是数组');
            container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">暂无订单</td></tr>';
            return;
        }

        const html = orders.map((order, index) => {
            console.log(`渲染第${index + 1}个订单:`, order);
            
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${order.order_number || '未知订单号'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div>
                            <div class="font-medium">${order.customer_name || '未知客户'}</div>
                            <div class="text-gray-400">${order.customer_phone || '未知电话'}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${order.device_type || '未知设备'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${order.device_model || '未知型号'}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        ${order.problem_description ? (order.problem_description.length > 50 ? order.problem_description.substring(0, 50) + '...' : order.problem_description) : '无描述'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusBadgeClass(order.status)}">
                            ${this.getStatusText(order.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${order.technician_name || '未分配'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end space-x-2">
                            <button onclick="app.viewOrder('${order.id}')" 
                                    class="text-blue-600 hover:text-blue-900" title="查看详情">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="app.editOrder('${order.id}')" 
                                    class="text-green-600 hover:text-green-900" title="编辑">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${order.status === 'ready' ? `
                                <button onclick="app.smsNotification.showSMSDialog('${order.id}')" 
                                        class="text-purple-600 hover:text-purple-900" title="发送短信">
                                    <i class="fas fa-sms"></i>
                                </button>
                            ` : ''}
                            ${order.status === 'pending' ? `
                                <button onclick="app.showAssignModal('${order.id}')" 
                                        class="text-orange-600 hover:text-orange-900" title="分配技术员">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        console.log('生成的HTML长度:', html.length);
        container.innerHTML = html;
        console.log('订单列表渲染完成');
    }

    // 查看订单详情
    async viewOrder(orderId) {
        try {
            const result = await API.getOrderById(orderId);
            if (result.success) {
                this.showOrderDetailsModal(result.data);
            }
        } catch (error) {
            console.error('获取订单详情失败:', error);
            this.showNotification('获取订单详情失败', 'error');
        }
    }

    // 显示订单详情模态框
    showOrderDetailsModal(order) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="orderDetailsModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">订单详情 - ${order.order_number}</h3>
                            <button onclick="document.getElementById('orderDetailsModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h4 class="font-medium text-gray-700 border-b pb-2">客户信息</h4>
                                <div class="space-y-2">
                                    <p><strong>姓名：</strong>${order.customer_name}</p>
                                    <p><strong>联系电话：</strong>${order.customer_phone}</p>
                                    <p><strong>QQ号：</strong>${order.customer_qq || '未填写'}</p>
                                    <p><strong>学院：</strong>${order.customer_college || '未填写'}</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <h4 class="font-medium text-gray-700 border-b pb-2">设备信息</h4>
                                <div class="space-y-2">
                                    <p><strong>设备类型：</strong>${order.device_type}</p>
                                    <p><strong>品牌型号：</strong>${order.device_brand || '未填写'}</p>
                                    <p><strong>故障描述：</strong>${order.problem_description}</p>
                                    <p><strong>预估费用：</strong>${order.estimated_cost || '待评估'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 space-y-4">
                            <h4 class="font-medium text-gray-700 border-b pb-2">订单状态</h4>
                            <div class="flex items-center space-x-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${this.getStatusBadgeClass(order.status)}">
                                    ${this.getStatusText(order.status)}
                                </span>
                                <p><strong>技术员：</strong>${order.technician_name || '未分配'}</p>
                                <p><strong>创建时间：</strong>${order.created_at}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            ${order.status === 'ready_for_pickup' ? `
                                <button onclick="app.smsNotification.showSMSDialog('${order.id}')" 
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                    发送短信
                                </button>
                            ` : ''}
                            <button onclick="app.editOrder('${order.id}')" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                编辑订单
                            </button>
                            <button onclick="document.getElementById('orderDetailsModal').remove()" 
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

    // 编辑订单
    async editOrder(orderId) {
        // 实现编辑订单功能
        this.showNotification('编辑订单功能开发中', 'info');
    }

    // 获取状态文本
    getStatusText(status) {
        const statusMap = {
            'pending': '待接单',
            'in_progress': '维修中',
            'ready_for_pickup': '待取机',
            'completed': '已完成',
            'cancelled': '已取消'
        };
        return statusMap[status] || status;
    }

    // 获取状态徽章样式
    getStatusBadgeClass(status) {
        const classMap = {
            'pending': 'bg-gray-100 text-gray-800',
            'in_progress': 'bg-blue-100 text-blue-800',
            'ready_for_pickup': 'bg-yellow-100 text-yellow-800',
            'completed': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };
        return classMap[status] || 'bg-gray-100 text-gray-800';
    }

    // 获取状态颜色
    getStatusColor(status) {
        const colorMap = {
            'pending': 'border-gray-400',
            'in_progress': 'border-blue-400',
            'ready_for_pickup': 'border-yellow-400',
            'completed': 'border-green-400',
            'cancelled': 'border-red-400'
        };
        return colorMap[status] || 'border-gray-400';
    }

    // 显示通知
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${
                    type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    type === 'warning' ? 'fa-exclamation-triangle' :
                    'fa-info-circle'
                } mr-2"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // 自动移除通知
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // 显示分配技术员模态框
    async showAssignModal(orderId) {
        try {
            // 获取技术员列表
            const techniciansResult = await API.getTechnicians();
            if (!techniciansResult.success) {
                this.showNotification('获取技术员列表失败', 'error');
                return;
            }

            const technicians = techniciansResult.data || [];
            const modalHtml = `
                <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="assignModal">
                    <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">分配技术员</h3>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">搜索技术员</label>
                                <input type="text" id="technicianSearchInput" placeholder="输入姓名或专业领域搜索" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mb-2">
                                <div class="relative">
                                    <select id="technicianSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">请选择技术员</option>
                                        ${technicians.map(tech => `
                                            <option value="${tech.id}" data-name="${tech.name}" data-specialty="${tech.specialty || '通用维修'}">${tech.name} - ${tech.specialty || '通用维修'}</option>
                                        `).join('')}
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button onclick="document.getElementById('assignModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button onclick="app.assignTechnician('${orderId}')" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    确认分配
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('modalContainer').innerHTML = modalHtml;
            
            // 绑定搜索事件
            document.getElementById('technicianSearchInput').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const select = document.getElementById('technicianSelect');
                const options = select.querySelectorAll('option');
                
                options.forEach(option => {
                    if (option.value === '') return; // 跳过"请选择技术员"选项
                    
                    const name = option.getAttribute('data-name').toLowerCase();
                    const specialty = option.getAttribute('data-specialty').toLowerCase();
                    
                    if (name.includes(searchTerm) || specialty.includes(searchTerm)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });
        } catch (error) {
            console.error('显示分配技术员模态框失败:', error);
            this.showNotification('显示分配技术员模态框失败', 'error');
        }
    }

    // 分配技术员
    async assignTechnician(orderId) {
        const technicianSelect = document.getElementById('technicianSelect');
        const technicianId = technicianSelect.value;
        
        if (!technicianId) {
            this.showNotification('请选择技术员', 'warning');
            return;
        }

        try {
            const result = await API.assignTechnician(orderId, technicianId);
            if (result.success) {
                this.showNotification('技术员分配成功', 'success');
                document.getElementById('assignModal').remove();
                
                // 刷新当前页面数据
                if (this.currentPage === 'technician-screen') {
                    this.screenDisplay.loadTechnicianScreenData();
                } else if (this.currentPage === 'order-management') {
                    this.loadOrders();
                }
            } else {
                this.showNotification('技术员分配失败: ' + (result.message || '未知错误'), 'error');
            }
        } catch (error) {
            console.error('分配技术员失败:', error);
            this.showNotification('分配技术员失败: ' + error.message, 'error');
        }
    }

    // 初始化活动管理页面
    async initActivityManagement() {
        try {
            console.log('初始化活动管理页面...');
            
            // 加载当前活动信息
            await this.loadCurrentActivity();
            
            // 加载活动列表
            await this.loadActivityList();
            
            // 绑定活动表单事件
            this.bindActivityFormEvents();
            
            console.log('活动管理页面初始化完成');
        } catch (error) {
            console.error('活动管理页面初始化失败:', error);
            this.showNotification('活动管理页面初始化失败', 'error');
        }
    }

    // 加载当前活动信息
    async loadCurrentActivity() {
        try {
            const result = await API.getActivities();
            if (result.success && result.data) {
                const currentActivity = result.data.find(activity => activity.is_current == 1);
                const container = document.getElementById('currentActivityInfo');
                
                if (container) {
                    if (currentActivity) {
                        container.innerHTML = `
                            <div class="space-y-2">
                                <h4 class="font-medium text-blue-900">${currentActivity.activity_name}</h4>
                                <p class="text-sm text-gray-600">日期: ${currentActivity.activity_date}</p>
                                <p class="text-sm text-gray-600">描述: ${currentActivity.description || '无描述'}</p>
                                <div class="flex items-center space-x-4 mt-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        当前活动
                                    </span>
                                    <button onclick="app.setCurrentActivity(null)" 
                                            class="text-sm text-red-600 hover:text-red-800">
                                        结束活动
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        container.innerHTML = `
                            <div class="text-center py-4">
                                <p class="text-gray-600 mb-2">暂无当前活动</p>
                                <p class="text-sm text-gray-500">请创建新活动或设置现有活动为当前活动</p>
                            </div>
                        `;
                    }
                }
            }
        } catch (error) {
            console.error('加载当前活动失败:', error);
        }
    }

    // 加载活动列表
    async loadActivityList() {
        try {
            const result = await API.getActivities();
            if (result.success && result.data) {
                const container = document.getElementById('activityList');
                if (container) {
                    if (result.data.length === 0) {
                        container.innerHTML = `
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    暂无活动记录
                                </td>
                            </tr>
                        `;
                    } else {
                        const html = result.data.map(activity => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="font-medium text-gray-900">${activity.activity_name}</div>
                                        ${activity.is_current == 1 ? `
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                当前
                                            </span>
                                        ` : ''}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${activity.activity_date}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${activity.order_count || 0}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        activity.is_current == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                    }">
                                        ${activity.is_current == 1 ? '进行中' : '已结束'}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        ${activity.is_current != 1 ? `
                                            <button onclick="app.setCurrentActivity('${activity.id}')" 
                                                    class="text-blue-600 hover:text-blue-900" title="设为当前活动">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        ` : ''}
                                        <button onclick="app.viewActivityDetails('${activity.id}')" 
                                                class="text-green-600 hover:text-green-900" title="查看详情">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="app.editActivity('${activity.id}')" 
                                                class="text-orange-600 hover:text-orange-900" title="编辑">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                        
                        container.innerHTML = html;
                    }
                }
            }
        } catch (error) {
            console.error('加载活动列表失败:', error);
        }
    }

    // 绑定活动表单事件
    bindActivityFormEvents() {
        const form = document.getElementById('activityForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.createActivity(new FormData(form));
            });
        }
    }

    // 创建新活动
    async createActivity(formData) {
        try {
            const activityData = {
                name: formData.get('activity_name'),
                activity_date: formData.get('activity_date'),
                description: formData.get('description')
            };

            console.log('创建活动数据:', activityData);

            const result = await API.createActivity(activityData);
            if (result.success) {
                this.showNotification('活动创建成功', 'success');
                
                // 重置表单
                document.getElementById('activityForm').reset();
                
                // 刷新活动列表
                await this.loadActivityList();
                await this.loadCurrentActivity();
            } else {
                this.showNotification('活动创建失败: ' + (result.message || '未知错误'), 'error');
            }
        } catch (error) {
            console.error('创建活动失败:', error);
            this.showNotification('创建活动失败: ' + error.message, 'error');
        }
    }

    // 设置当前活动
    async setCurrentActivity(activityId) {
        try {
            const result = await API.setCurrentActivity(activityId);
            if (result.success) {
                this.showNotification(activityId ? '当前活动设置成功' : '活动已结束', 'success');
                
                // 刷新活动信息
                await this.loadCurrentActivity();
                await this.loadActivityList();
                
                // 更新全局当前活动
                this.currentActivity = activityId;
            } else {
                this.showNotification('操作失败: ' + (result.message || '未知错误'), 'error');
            }
        } catch (error) {
            console.error('设置当前活动失败:', error);
            this.showNotification('操作失败: ' + error.message, 'error');
        }
    }

    // 查看活动详情
    async viewActivityDetails(activityId) {
        try {
            const result = await API.getActivityById(activityId);
            if (result.success) {
                this.showActivityDetailsModal(result.data);
            } else {
                this.showNotification('获取活动详情失败', 'error');
            }
        } catch (error) {
            console.error('获取活动详情失败:', error);
            this.showNotification('获取活动详情失败', 'error');
        }
    }

    // 显示活动详情模态框
    showActivityDetailsModal(activity) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="activityDetailsModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">活动详情 - ${activity.name}</h3>
                            <button onclick="document.getElementById('activityDetailsModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">活动名称</label>
                                    <p class="mt-1 text-sm text-gray-900">${activity.name}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">活动日期</label>
                                    <p class="mt-1 text-sm text-gray-900">${activity.activity_date}</p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">活动描述</label>
                                <p class="mt-1 text-sm text-gray-900">${activity.description || '无描述'}</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">总订单数</label>
                                    <p class="mt-1 text-2xl font-bold text-blue-600">${activity.order_count || 0}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">已完成</label>
                                    <p class="mt-1 text-2xl font-bold text-green-600">${activity.completed_count || 0}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">状态</label>
                                    <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        activity.is_current == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                    }">
                                        ${activity.is_current == 1 ? '进行中' : '已结束'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            ${activity.is_current != 1 ? `
                                <button onclick="app.setCurrentActivity('${activity.id}'); document.getElementById('activityDetailsModal').remove();" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    设为当前活动
                                </button>
                            ` : ''}
                            <button onclick="app.editActivity('${activity.id}'); document.getElementById('activityDetailsModal').remove();" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                编辑活动
                            </button>
                            <button onclick="document.getElementById('activityDetailsModal').remove()" 
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

    // 编辑活动
    async editActivity(activityId) {
        // 实现编辑活动功能
        this.showNotification('编辑活动功能开发中', 'info');
    }

    // 确认对话框
    showConfirm(message, callback) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="confirmModal">
                <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">确认操作</h3>
                        <p class="text-sm text-gray-500 mb-6">${message}</p>
                        <div class="flex justify-center space-x-3">
                            <button onclick="document.getElementById('confirmModal').remove()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                取消
                            </button>
                            <button onclick="document.getElementById('confirmModal').remove(); (${callback})()" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                确认
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
    }
}

// 全局应用实例
let app;

// 页面加载完成后初始化应用
document.addEventListener('DOMContentLoaded', async () => {
    try {
        app = new App();
        await app.init();
    } catch (error) {
        console.error('应用启动失败:', error);
        alert('系统启动失败，请刷新页面重试');
    }
});