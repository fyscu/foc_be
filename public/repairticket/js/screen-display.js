// 大屏显示功能管理
class ScreenDisplay {
    constructor(app) {
        this.app = app;
        this.refreshInterval = null;
        this.refreshRate = 10000; // 10秒刷新一次
    }

    // 通用初始化方法
    init() {
        console.log('初始化大屏显示管理器...');
        // 等待DOM元素渲染完成后再初始化
        setTimeout(() => {
            // 根据当前页面类型初始化对应的大屏
            if (this.app.currentPage === 'technician-screen') {
                this.initTechnicianScreen();
            } else if (this.app.currentPage === 'service-screen') {
                this.initServiceScreen();
            }
        }, 100);
    }

    // 初始化6号位技术员大屏
    initTechnicianScreen() {
        this.loadTechnicianScreenData();
        this.startAutoRefresh('technician');
        
        // 绑定事件
        this.bindTechnicianScreenEvents();
    }

    // 初始化5号位客服大屏
    initServiceScreen() {
        this.loadServiceScreenData();
        this.startAutoRefresh('service');
        
        // 绑定事件
        this.bindServiceScreenEvents();
    }

    // 加载技术员大屏数据 (6号位 - 处理待接单和维修中的订单)
    async loadTechnicianScreenData() {
        try {
            console.log('开始加载6号位技术员大屏数据...');
            
            // 获取待接单的订单 (pending)
            const pendingOrdersResult = await API.getOrders({ 
                status: 'pending',
                limit: 100 
            });
            console.log('6号位待接单订单API返回:', pendingOrdersResult);
            
            // 获取维修中的订单 (processing)
            const processingOrdersResult = await API.getOrders({ 
                status: 'processing',
                limit: 100 
            });
            console.log('6号位维修中订单API返回:', processingOrdersResult);
            
            // 获取技术员列表
            const techniciansResult = await API.getTechnicians();
            console.log('技术员列表API返回:', techniciansResult);
            
            // 获取统计数据
            const statsResult = await API.getOrderStats();
            console.log('统计数据API返回:', statsResult);

            if (pendingOrdersResult.success && processingOrdersResult.success && techniciansResult.success && statsResult.success) {
                const pendingOrders = pendingOrdersResult.data.orders || pendingOrdersResult.data || [];
                const processingOrders = processingOrdersResult.data.orders || processingOrdersResult.data || [];
                
                console.log('6号位解析的待接单订单数据:', pendingOrders);
                console.log('6号位解析的维修中订单数据:', processingOrders);
                console.log('6号位待接单订单数量:', pendingOrders.length);
                console.log('6号位维修中订单数量:', processingOrders.length);
                
                this.renderTechnicianScreen(
                    pendingOrders,
                    processingOrders,
                    techniciansResult.data,
                    statsResult.data
                );
            } else {
                console.error('6号位大屏API调用失败:', {
                    pendingOrders: pendingOrdersResult,
                    processingOrders: processingOrdersResult,
                    technicians: techniciansResult,
                    stats: statsResult
                });
            }
        } catch (error) {
            console.error('加载6号位大屏数据失败:', error);
        }
    }

    // 加载客服大屏数据 (5号位 - 处理维修中和待取机的订单)
    async loadServiceScreenData() {
        try {
            console.log('开始加载5号位客服大屏数据...');
            
            // 5号位需要获取维修中的订单 (processing)
            const processingOrdersResult = await API.getOrders({ 
                status: 'processing',
                limit: 100 
            });
            console.log('5号位维修中订单API返回:', processingOrdersResult);
            
            // 5号位需要获取待取机的订单 (ready)
            const readyOrdersResult = await API.getOrders({ 
                status: 'ready',
                limit: 100 
            });
            console.log('5号位待取机订单API返回:', readyOrdersResult);

            // 获取统计数据
            const statsResult = await API.getOrderStats();
            console.log('5号位统计数据API返回:', statsResult);

            if (processingOrdersResult.success && readyOrdersResult.success && statsResult.success) {
                const processingOrders = processingOrdersResult.data.orders || processingOrdersResult.data || [];
                const readyOrders = readyOrdersResult.data.orders || readyOrdersResult.data || [];
                
                console.log('5号位解析的维修中订单:', processingOrders);
                console.log('5号位解析的待取机订单:', readyOrders);
                console.log('5号位维修中订单数量:', processingOrders.length);
                console.log('5号位待取机订单数量:', readyOrders.length);
                
                this.renderServiceScreen(
                    processingOrders,
                    readyOrders,
                    statsResult.data
                );
            } else {
                console.error('5号位大屏API调用失败:', {
                    processingOrders: processingOrdersResult,
                    readyOrders: readyOrdersResult,
                    stats: statsResult
                });
            }
        } catch (error) {
            console.error('加载5号位大屏数据失败:', error);
        }
    }

    // 渲染技术员大屏 (6号位 - 显示待接单和维修中订单)
    renderTechnicianScreen(pendingOrders, processingOrders, technicians, stats) {
        // 更新统计数据
        const pendingCount = document.getElementById('techPendingCount');
        const processingCount = document.getElementById('techProcessingCount');
        const onlineTechCount = document.getElementById('onlineTechCount');

        if (pendingCount) pendingCount.textContent = stats.pending || pendingOrders.length;
        if (processingCount) processingCount.textContent = stats.in_progress || processingOrders.length;
        if (onlineTechCount) {
            const onlineCount = technicians.filter(t => t.status === 'online').length;
            onlineTechCount.textContent = stats.online_technicians || onlineCount;
        }

        // 渲染订单列表
        const container = document.getElementById('techOrderList');
        if (!container) return;

        // 如果没有任何订单
        if (pendingOrders.length === 0 && processingOrders.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 py-8">暂无订单</div>';
            return;
        }

        // 待接单订单部分
        const pendingHtml = pendingOrders.length > 0 ? `
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-yellow-600 mb-1 pb-1">
                    待接单订单 (${pendingOrders.length})
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">订单号</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客户</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">设备</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">故障</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创建时间</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${pendingOrders.map(order => `
                                <tr class="hover:bg-gray-50 bg-yellow-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center">
                                            <span class="text-lg font-bold text-yellow-600">
                                                ${order.order_number}
                                            </span>
                                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                待接单
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium">${order.customer_name}</div>
                                            <div class="text-gray-500">${order.customer_phone}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <div class="font-medium">${order.device_type}</div>
                                            <div class="text-xs text-gray-400">${order.device_model || '未知型号'}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                                        <div class="truncate" title="${order.problem_description}">
                                            ${order.problem_description}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${order.created_at}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="app.showAssignModal('${order.id}')" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">
                                            分配技术员
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` : '';

        // 维修中订单部分
        const processingHtml = processingOrders.length > 0 ? `
            <div class="mb-4"> 
                <h3 class="mt-4 text-lg font-semibold text-blue-600 mb-1 pb-1">
                    已分配订单 (${processingOrders.length})
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">订单号</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">客户</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">设备</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">故障</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">技术员</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            ${processingOrders.map(order => `
                                <tr class="hover:bg-gray-50 bg-blue-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center">
                                            <span class="text-lg font-bold text-blue-600">
                                                ${order.order_number}
                                            </span>
                                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                维修中
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium">${order.customer_name}</div>
                                            <div class="text-gray-500">${order.customer_phone}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <div class="font-medium">${order.device_type}</div>
                                            <div class="text-xs text-gray-400">${order.device_model || '未知型号'}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                                        <div class="truncate" title="${order.problem_description}">
                                            ${order.problem_description}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <div class="font-medium">${order.technician_name || '未知'}</div>
                                            <div class="text-xs text-gray-400">开始时间: ${order.updated_at}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="app.showAssignModal('${order.id}')" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">
                                            重新分配技术员
                                        </button>
                                        <button onclick="app.screenDisplay.showRepairQRCode('${order.id}', '${order.order_number}')" 
                                                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm font-medium">
                                            显示维修二维码
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` : '';

        container.innerHTML = pendingHtml + processingHtml;
    }

    // 渲染客服大屏 (5号位 - 显示维修中和待取机订单)
    renderServiceScreen(processingOrders, readyOrders, stats) {
        // 更新统计数据
        const serviceReadyCount = document.getElementById('serviceReadyCount');
        const todayCompletedCount = document.getElementById('todayCompletedCount');

        if (serviceReadyCount) serviceReadyCount.textContent = stats.ready_for_pickup || readyOrders.length;
        if (todayCompletedCount) todayCompletedCount.textContent = stats.completed || 0;

        // 渲染订单列表 - 分为维修中和待取机两个部分
        const container = document.getElementById('serviceOrderList');
        if (!container) return;

        // 维修中订单部分
        const processingHtml = processingOrders.length > 0 ? `
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-blue-600 mb-1 pb-1">
                    维修中订单 (${processingOrders.length})
                </h3>
                <div class="space-y-2">
                    ${processingOrders.map(order => `
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-lg font-bold text-blue-600">${order.order_number}</span>
                                        <span class="text-sm text-gray-600">${order.customer_name}</span>
                                        <span class="text-sm text-gray-500">${order.customer_phone}</span>
                                        <span class="text-sm text-gray-500">${order.device_type}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        技术员: ${order.technician_name || '未分配'} | 开始时间: ${order.updated_at}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="app.workflow.showStatusTransitionDialog('${order.id}', 'processing', 'ready')" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                        标记完成
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        ` : '';

        // 待取机订单部分
        const readyHtml = readyOrders.length > 0 ? `
            <div>
                <h3 class="text-lg font-semibold text-orange-600 mb-1 pb-1">
                    待取机订单 (${readyOrders.length})
                </h3>
                <div class="space-y-2">
                    ${readyOrders.map(order => `
                        <div class="bg-orange-50 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-lg font-bold text-orange-600">${order.order_number}</span>
                                        <span class="text-sm font-medium text-gray-900">${order.customer_name}</span>
                                        <span class="text-sm font-medium text-gray-900">${order.customer_phone}</span>
                                        <span class="text-sm text-gray-600">${order.device_type}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        完成时间: ${order.updated_at} | 技术员: ${order.technician_name || '未知'}
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="app.screenDisplay.showSMSQRCode('${order.id}', '${order.customer_phone}', '${order.customer_name}', '${order.order_number}')" 
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm ${order.sms_sent == 1 ? 'opacity-50' : ''}"
                                            title="${order.sms_sent == 1 ? '短信已发送' : '点击显示短信二维码'}">
                                        ${order.sms_sent == 1 ? '已标记发送' : '去发送短信'}
                                    </button>

                                    <button onclick="app.screenDisplay.showPickupModal('${order.id}', '${order.order_number}')" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                        客户取机
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        ` : '';

        // 如果没有任何订单
        if (processingOrders.length === 0 && readyOrders.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 py-8">暂无订单</div>';
            return;
        }

        container.innerHTML = processingHtml + readyHtml;
    }

    // 绑定技术员大屏事件
    bindTechnicianScreenEvents() {
        // 添加快捷键支持
        document.addEventListener('keydown', (e) => {
            if (this.app.currentPage !== 'technician-screen') return;
            
            // F5 刷新
            if (e.key === 'F5') {
                e.preventDefault();
                this.loadTechnicianScreenData();
            }
            
            // 空格键暂停/恢复自动刷新
            if (e.code === 'Space') {
                e.preventDefault();
                this.toggleAutoRefresh();
            }
        });
    }

    // 绑定客服大屏事件
    bindServiceScreenEvents() {
        // 添加快捷键支持
        document.addEventListener('keydown', (e) => {
            if (this.app.currentPage !== 'service-screen') return;
            
            // F5 刷新
            if (e.key === 'F5') {
                e.preventDefault();
                this.loadServiceScreenData();
            }
            
            // 空格键暂停/恢复自动刷新
            if (e.code === 'Space') {
                e.preventDefault();
                this.toggleAutoRefresh();
            }
        });
    }

    // 开始自动刷新
    startAutoRefresh(screenType) {
        this.stopAutoRefresh(); // 先停止之前的刷新
        
        this.refreshInterval = setInterval(() => {
            if (screenType === 'technician' && this.app.currentPage === 'technician-screen') {
                this.loadTechnicianScreenData();
            } else if (screenType === 'service' && this.app.currentPage === 'service-screen') {
                this.loadServiceScreenData();
            }
        }, this.refreshRate);
    }

    // 停止自动刷新
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    // 切换自动刷新
    toggleAutoRefresh() {
        if (this.refreshInterval) {
            this.stopAutoRefresh();
            this.app.showNotification('自动刷新已暂停，按空格键恢复', 'info');
        } else {
            const screenType = this.app.currentPage === 'technician-screen' ? 'technician' : 'service';
            this.startAutoRefresh(screenType);
            this.app.showNotification('自动刷新已恢复', 'success');
        }
    }

    // 设置刷新频率
    setRefreshRate(rate) {
        this.refreshRate = rate * 1000; // 转换为毫秒
        
        // 如果正在刷新，重新启动
        if (this.refreshInterval) {
            const screenType = this.app.currentPage === 'technician-screen' ? 'technician' : 'service';
            this.startAutoRefresh(screenType);
        }
    }

    // 全屏显示
    enterFullscreen() {
        const element = document.documentElement;
        if (element.requestFullscreen) {
            element.requestFullscreen();
        } else if (element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
        } else if (element.msRequestFullscreen) {
            element.msRequestFullscreen();
        }
    }

    // 退出全屏
    exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }

    // 显示短信二维码
    async showSMSQRCode(orderId, phone, customerName, orderNumber) {
        // 构建短信内容
        var ua = navigator.userAgent;
        const smsContent = `【飞扬俱乐部】您好${customerName}同学，您的设备（订单号：${orderNumber}）已维修完成，请及时到现场取回。如有疑问请联系我们。`;
        
        // 构建短信URL（适用于iOS和Android）
        // const smsUrl = `sms:${phone}?body=${encodeURIComponent(smsContent)}`;
        let smsUrl = `sms:${phone}&body=${encodeURIComponent(smsContent)}`;
        if (/(iPhone|iPad|iPod|iOS)/i.test(ua)) {
            smsUrl = `https://focapp.feiyang.ac.cn/public/message/repairdone?phone=${phone}&message=${encodeURIComponent(smsContent)}`;
	        // smsUrl = `sms:${phone}&body=${encodeURIComponent(smsContent)}`;
        } else {
            smsUrl = `https://focapp.feiyang.ac.cn/public/message/repairdone?phone=${phone}&message=${encodeURIComponent(smsContent)}`;
            // smsUrl = `sms:${phone}?body=${encodeURIComponent(smsContent)}`;
        }
        // 创建模态框
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">扫码发送短信</h3>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">订单号：${orderNumber}</p>
                        <p class="text-sm text-gray-600 mb-2">客户：${customerName}</p>
                        <p class="text-sm text-gray-600 mb-4">手机：${phone}</p>
                    </div>
                    
                    <!-- 二维码容器 -->
                    <div id="qrcode-container" class="flex justify-center mb-4">
                        <div class="w-48 h-48 border-2 border-gray-300 rounded-lg flex items-center justify-center">
                            <p id="qrp">二维码加载中...</p>
                            <div id="qrcode-${orderId}"></div>
                        </div>
                    </div>
                    
                    <div class="text-xs text-gray-500 mb-4 px-4">
                        <p class="mb-2">短信内容：</p>
                        <p class="bg-gray-100 p-2 rounded text-left">${smsContent}</p>
                    </div>
                    
                    <div class="text-sm text-blue-600 mb-4">
                        <p>请使用手机扫描二维码</p>
                        <p>自动打开短信应用发送通知</p>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            关闭
                        </button>
                        <button onclick="app.screenDisplay.markSMSSent('${orderId}'); this.parentElement.parentElement.parentElement.parentElement.remove();" 
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                            标记已发送
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // 生成二维码
        this.generateQRCode(`qrcode-${orderId}`, smsUrl);
        // 取消qrp的显示
        const qrPlaceholder = document.getElementById('qrp');
        if (qrPlaceholder) {
            qrPlaceholder.style.display = 'none';
        }
            
        // 点击背景关闭
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    // 显示维修记录二维码
    showRepairQRCode(orderId, orderNumber) {
        // 构建维修记录URL
        const repairUrl = `${window.location.origin}/public/repairticket/api/technician_repair.php?id=${orderId}`;
        
        // 创建模态框
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">维修记录二维码</h3>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">订单号：${orderNumber}</p>
                    </div>
                    
                    <!-- 二维码容器 -->
                    <div id="qrcode-container" class="flex justify-center mb-4">
                        <div class="w-48 h-48 border-2 border-gray-300 rounded-lg flex items-center justify-center">
                            <p id="repair-qrp">二维码加载中...</p>
                            <div id="repair-qrcode-${orderId}"></div>
                        </div>
                    </div>
                    
                    <div class="text-sm text-blue-600 mb-4">
                        <p>请技术员扫描二维码</p>
                        <p>填写维修记录</p>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" 
                                class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            关闭
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // 生成二维码
        this.generateQRCode(`repair-qrcode-${orderId}`, repairUrl);
        // 取消qrp的显示
        const qrPlaceholder = document.getElementById('repair-qrp');
        if (qrPlaceholder) {
            qrPlaceholder.style.display = 'none';
        }
            
        // 点击背景关闭
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    // 生成二维码
    generateQRCode(containerId, text) {
        // 使用简单的二维码生成方法
        // 这里使用在线二维码API服务
        const qrContainer = document.getElementById(containerId);
        if (qrContainer) {
            const qrUrl = `https://api.pwmqr.com/qrcode/create/?url=${encodeURIComponent(text)}`;
            qrContainer.innerHTML = `<img src="${qrUrl}" alt="二维码" class="w-full h-full object-contain" />`;
        }
    }

    // 标记短信已发送
    async markSMSSent(orderId) {
        try {
            console.log('标记订单短信已发送:', orderId);
            
            // 调用API标记短信已发送
            const result = await API.markSMSSent(orderId);
            
            if (result.success) {
                this.app.showNotification('短信发送状态已更新', 'success');
                // 刷新当前页面数据
                if (this.app.currentPage === 'service-screen') {
                    this.loadServiceScreenData();
                }
            } else {
                this.app.showNotification('更新失败：' + (result.message || '未知错误'), 'error');
            }
        } catch (error) {
            console.error('标记短信发送状态失败:', error);
            this.app.showNotification('标记失败，请重试', 'error');
        }
    }

    // 显示客户取机确认弹窗
    showPickupModal(orderId, orderNumber) {
        // 创建弹窗
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="fixed inset-0 bg-black opacity-50"></div>
            <div class="bg-white rounded-lg shadow-xl z-10 w-full max-w-lg mx-4">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">客户取机确认 - ${orderNumber}</h3>
                </div>
                <form id="pickupForm" class="p-6">
                    <input type="hidden" name="order_id" value="${orderId}">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">取机通知时间</label>
                        <input type="datetime-local" name="pickup_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">机主确认签字</label>
                        <div id="signatureCanvas" class="border border-gray-300 rounded-md h-40 bg-gray-50 flex items-center justify-center">
                            <canvas id="customerSignature" width="400" height="150" class="cursor-crosshair"></canvas>
                        </div>
                        <input type="hidden" name="customer_signature" id="customerSignatureData">
                        <div class="flex justify-end mt-2">
                            <button type="button" id="clearSignature" class="text-sm text-blue-600 hover:text-blue-800">
                                清除签名
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-4 border-t">
                        <button type="button" id="cancelPickup" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            取消
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            确认完成
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // 初始化签名画布
        this.initSignatureCanvas();
        
        // 绑定弹窗事件
        document.getElementById('cancelPickup').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        document.getElementById('clearSignature').addEventListener('click', () => {
            this.clearSignature();
        });
        
        document.getElementById('pickupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // 获取表单数据
            const formData = new FormData(e.target);
            const pickupData = Object.fromEntries(formData.entries());
            
            // 验证签名
            if (!pickupData.customer_signature) {
                this.app.showNotification('请客户完成签名确认', 'error');
                return;
            }
            
            try {
                // 提交取机确认
                const result = await this.completeOrder(pickupData);
                
                if (result.success) {
                    this.app.showNotification('订单已完成', 'success');
                    document.body.removeChild(modal);
                    this.loadServiceScreenData(); // 重新加载订单列表
                } else {
                    this.app.showNotification('操作失败: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('提交取机确认失败:', error);
                this.app.showNotification('操作失败，请重试', 'error');
            }
        });
    }
    
    // 初始化签名画布
    initSignatureCanvas() {
        const canvas = document.getElementById('customerSignature');
        const ctx = canvas.getContext('2d');
        const signatureInput = document.getElementById('customerSignatureData');
        
        // 调整画布大小以适应容器
        const resizeCanvas = () => {
            const container = canvas.parentElement;
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            
            // 重新设置画布样式
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#000';
        };
        
        // 初始调整画布大小
        resizeCanvas();
        
        // 窗口大小变化时重新调整
        window.addEventListener('resize', resizeCanvas);
        
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;
        
        // 鼠标事件处理
        function startDrawing(e) {
            isDrawing = true;
            const pos = getPosition(e);
            lastX = pos.x;
            lastY = pos.y;
        }
        
        function draw(e) {
            if (!isDrawing) return;
            
            const pos = getPosition(e);
            
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            
            lastX = pos.x;
            lastY = pos.y;
            
            // 保存签名数据
            signatureInput.value = canvas.toDataURL();
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        // 获取鼠标或触摸的位置
        function getPosition(e) {
            const rect = canvas.getBoundingClientRect();
            let x, y;
            
            // 触摸事件
            if (e.type.includes('touch')) {
                const touch = e.touches[0] || e.changedTouches[0];
                x = touch.clientX - rect.left;
                y = touch.clientY - rect.top;
            } 
            // 鼠标事件
            else {
                x = e.clientX - rect.left;
                y = e.clientY - rect.top;
            }
            
            return { x, y };
        }
        
        // 绑定鼠标事件
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // 绑定触摸事件
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault(); // 防止滚动
            startDrawing(e);
        });
        
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault(); // 防止滚动
            draw(e);
        });
        
        canvas.addEventListener('touchend', function(e) {
            e.preventDefault(); // 防止滚动
            stopDrawing();
        });
    }
    
    // 清除签名
    clearSignature() {
        const canvas = document.getElementById('customerSignature');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('customerSignatureData').value = '';
    }

    // 完成订单
    async completeOrder(data) {
        try {
            console.log('提交客户取机确认:', data);
            
            // 调用API完成订单
            const response = await fetch('api/complete_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: data.order_id,
                    pickup_time: data.pickup_time,
                    customer_signature: data.customer_signature
                })
            });
            
            return await response.json();
        } catch (error) {
            console.error('完成订单API调用失败:', error);
            throw error;
        }
    }

    // 销毁大屏显示
    destroy() {
        this.stopAutoRefresh();
    }
}