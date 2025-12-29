// 短信通知管理模块
class SMSNotification {
    constructor(app) {
        this.app = app;
        this.smsTemplates = {
            'repair_completed': {
                name: '维修完成通知',
                template: '【飞扬俱乐部】您好，您的设备{device_type}已维修完成，请携带取机凭证到现场取机。订单号：{order_number}，联系电话：028-85405110',
                variables: ['device_type', 'order_number']
            },
            'repair_started': {
                name: '维修开始通知',
                template: '【飞扬俱乐部】您好，您的设备{device_type}已开始维修，我们会尽快完成。订单号：{order_number}',
                variables: ['device_type', 'order_number']
            },
            'repair_delayed': {
                name: '维修延期通知',
                template: '【飞扬俱乐部】抱歉，您的设备{device_type}维修需要延期，预计{estimated_time}完成。订单号：{order_number}',
                variables: ['device_type', 'order_number', 'estimated_time']
            },
            'custom': {
                name: '自定义消息',
                template: '',
                variables: []
            }
        };
        this.sendingQueue = [];
        this.sendHistory = [];
    }

    // 发送单条短信
    async sendSMS(orderId, templateType = 'repair_completed', customMessage = '', variables = {}) {
        try {
            // 获取订单信息
            const orderResult = await API.getOrderById(orderId);
            if (!orderResult.success) {
                throw new Error('获取订单信息失败');
            }

            const order = orderResult.data;
            let message = '';

            if (templateType === 'custom' && customMessage) {
                message = customMessage;
            } else {
                const template = this.smsTemplates[templateType];
                if (!template) {
                    throw new Error('短信模板不存在');
                }

                message = this.renderTemplate(template.template, {
                    device_type: order.device_type,
                    order_number: order.order_number,
                    customer_name: order.customer_name,
                    ...variables
                });
            }

            // 发送短信
            const smsResult = await API.sendSMS({
                order_id: orderId,
                phone: order.customer_phone,
                message: message,
                template_type: templateType
            });

            if (smsResult.success) {
                // 记录发送历史
                this.addToHistory({
                    order_id: orderId,
                    order_number: order.order_number,
                    customer_name: order.customer_name,
                    phone: order.customer_phone,
                    message: message,
                    template_type: templateType,
                    status: 'sent',
                    sent_at: new Date().toISOString()
                });

                this.app.showNotification(`短信发送成功：${order.customer_name}`, 'success');
                return { success: true, data: smsResult.data };
            } else {
                throw new Error(smsResult.message || '短信发送失败');
            }

        } catch (error) {
            console.error('发送短信失败:', error);
            this.app.showNotification('发送短信失败：' + error.message, 'error');
            return { success: false, error: error.message };
        }
    }

    // 批量发送短信
    async batchSendSMS(orderIds, templateType = 'repair_completed', customMessage = '') {
        const results = [];
        const total = orderIds.length;
        let successCount = 0;
        let failCount = 0;

        // 显示进度提示
        this.showBatchProgress(0, total);

        for (let i = 0; i < orderIds.length; i++) {
            const orderId = orderIds[i];
            
            try {
                const result = await this.sendSMS(orderId, templateType, customMessage);
                results.push({ orderId, ...result });
                
                if (result.success) {
                    successCount++;
                } else {
                    failCount++;
                }

                // 更新进度
                this.updateBatchProgress(i + 1, total, successCount, failCount);
                
                // 避免发送过快，间隔500ms
                if (i < orderIds.length - 1) {
                    await this.delay(500);
                }

            } catch (error) {
                results.push({ orderId, success: false, error: error.message });
                failCount++;
                this.updateBatchProgress(i + 1, total, successCount, failCount);
            }
        }

        // 隐藏进度提示
        this.hideBatchProgress();

        // 显示批量发送结果
        this.showBatchResult(successCount, failCount, total);

        return results;
    }

    // 渲染短信模板
    renderTemplate(template, variables) {
        let message = template;
        
        Object.keys(variables).forEach(key => {
            const placeholder = `{${key}}`;
            message = message.replace(new RegExp(placeholder, 'g'), variables[key] || '');
        });

        return message;
    }

    // 显示短信发送对话框
    showSMSDialog(orderId, defaultTemplate = 'repair_completed') {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="smsModal">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">发送短信通知</h3>
                            <button onclick="document.getElementById('smsModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form id="smsForm" class="space-y-4">
                            <input type="hidden" name="order_id" value="${orderId}">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">短信模板</label>
                                <select name="template_type" id="templateSelect" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    ${Object.keys(this.smsTemplates).map(key => `
                                        <option value="${key}" ${key === defaultTemplate ? 'selected' : ''}>
                                            ${this.smsTemplates[key].name}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">短信内容预览</label>
                                <textarea id="messagePreview" readonly 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm"
                                          rows="4"></textarea>
                                <div class="text-right text-xs text-gray-500 mt-1">
                                    字符数：<span id="charCount">0</span>/70
                                </div>
                            </div>
                            
                            <div id="customMessageDiv" style="display: none;">
                                <label class="block text-sm font-medium text-gray-700 mb-2">自定义消息</label>
                                <textarea name="custom_message" id="customMessage" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          rows="3" placeholder="请输入自定义短信内容"></textarea>
                            </div>
                            
                            <div id="variablesDiv">
                                <label class="block text-sm font-medium text-gray-700 mb-2">模板变量</label>
                                <div id="variableInputs" class="space-y-2"></div>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="document.getElementById('smsModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <i class="fas fa-paper-plane mr-2"></i>发送短信
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
        
        // 绑定事件
        this.bindSMSDialogEvents(orderId);
        
        // 初始化模板预览
        this.updateTemplatePreview(orderId, defaultTemplate);
    }

    // 绑定短信对话框事件
    bindSMSDialogEvents(orderId) {
        const form = document.getElementById('smsForm');
        const templateSelect = document.getElementById('templateSelect');
        const customMessage = document.getElementById('customMessage');
        
        // 模板选择变化
        templateSelect.addEventListener('change', (e) => {
            this.updateTemplatePreview(orderId, e.target.value);
        });
        
        // 自定义消息输入
        customMessage?.addEventListener('input', (e) => {
            this.updateMessagePreview(e.target.value);
        });
        
        // 表单提交
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleSMSFormSubmit(form);
        });
    }

    // 更新模板预览
    async updateTemplatePreview(orderId, templateType) {
        try {
            const orderResult = await API.getOrderById(orderId);
            if (!orderResult.success) return;

            const order = orderResult.data;
            const template = this.smsTemplates[templateType];
            
            const customDiv = document.getElementById('customMessageDiv');
            const variablesDiv = document.getElementById('variablesDiv');
            
            if (templateType === 'custom') {
                customDiv.style.display = 'block';
                variablesDiv.style.display = 'none';
                this.updateMessagePreview('');
            } else {
                customDiv.style.display = 'none';
                variablesDiv.style.display = 'block';
                
                // 生成变量输入框
                this.generateVariableInputs(template.variables);
                
                // 预览消息
                const message = this.renderTemplate(template.template, {
                    device_type: order.device_type,
                    order_number: order.order_number,
                    customer_name: order.customer_name
                });
                
                this.updateMessagePreview(message);
            }
            
        } catch (error) {
            console.error('更新模板预览失败:', error);
        }
    }

    // 生成变量输入框
    generateVariableInputs(variables) {
        const container = document.getElementById('variableInputs');
        if (!container) return;

        const html = variables.map(variable => `
            <div class="flex items-center space-x-2">
                <label class="w-24 text-sm text-gray-600">{${variable}}:</label>
                <input type="text" name="var_${variable}" 
                       class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm"
                       placeholder="输入${variable}的值">
            </div>
        `).join('');
        
        container.innerHTML = html;
        
        // 绑定变量输入事件
        container.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                this.updateVariablePreview();
            });
        });
    }

    // 更新变量预览
    updateVariablePreview() {
        const templateSelect = document.getElementById('templateSelect');
        const templateType = templateSelect.value;
        
        if (templateType === 'custom') return;
        
        const template = this.smsTemplates[templateType];
        const variables = {};
        
        // 收集变量值
        template.variables.forEach(variable => {
            const input = document.querySelector(`input[name="var_${variable}"]`);
            variables[variable] = input ? input.value : '';
        });
        
        const message = this.renderTemplate(template.template, variables);
        this.updateMessagePreview(message);
    }

    // 更新消息预览
    updateMessagePreview(message) {
        const preview = document.getElementById('messagePreview');
        const charCount = document.getElementById('charCount');
        
        if (preview) {
            preview.value = message;
        }
        
        if (charCount) {
            charCount.textContent = message.length;
            charCount.className = message.length > 70 ? 'text-red-500' : 'text-gray-500';
        }
    }

    // 处理短信表单提交
    async handleSMSFormSubmit(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        const orderId = data.order_id;
        const templateType = data.template_type;
        const customMessage = data.custom_message || '';
        
        // 收集变量
        const variables = {};
        Object.keys(data).forEach(key => {
            if (key.startsWith('var_')) {
                const varName = key.replace('var_', '');
                variables[varName] = data[key];
            }
        });
        
        // 发送短信
        const result = await this.sendSMS(orderId, templateType, customMessage, variables);
        
        if (result.success) {
            document.getElementById('smsModal').remove();
            
            // 刷新相关视图
            if (this.app.currentPage === 'service-screen') {
                this.app.screenDisplay.refreshServiceScreen();
            } else if (this.app.currentPage === 'order-management') {
                this.app.loadOrders();
            }
        }
    }

    // 显示批量短信对话框
    showBatchSMSDialog(orderIds) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="batchSMSModal">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">批量发送短信</h3>
                            <button onclick="document.getElementById('batchSMSModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                将向 ${orderIds.length} 个订单的客户发送短信通知
                            </p>
                        </div>
                        
                        <form id="batchSMSForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">短信模板</label>
                                <select name="template_type" id="batchTemplateSelect" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    ${Object.keys(this.smsTemplates).filter(key => key !== 'custom').map(key => `
                                        <option value="${key}">
                                            ${this.smsTemplates[key].name}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">模板预览</label>
                                <textarea id="batchMessagePreview" readonly 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm"
                                          rows="3"></textarea>
                                <p class="text-xs text-gray-500 mt-1">
                                    实际发送时会自动替换订单相关变量
                                </p>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" onclick="document.getElementById('batchSMSModal').remove()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    取消
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <i class="fas fa-paper-plane mr-2"></i>批量发送
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
        
        // 绑定事件
        this.bindBatchSMSDialogEvents(orderIds);
        
        // 初始化预览
        this.updateBatchTemplatePreview('repair_completed');
    }

    // 绑定批量短信对话框事件
    bindBatchSMSDialogEvents(orderIds) {
        const form = document.getElementById('batchSMSForm');
        const templateSelect = document.getElementById('batchTemplateSelect');
        
        // 模板选择变化
        templateSelect.addEventListener('change', (e) => {
            this.updateBatchTemplatePreview(e.target.value);
        });
        
        // 表单提交
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const templateType = templateSelect.value;
            
            document.getElementById('batchSMSModal').remove();
            await this.batchSendSMS(orderIds, templateType);
        });
    }

    // 更新批量模板预览
    updateBatchTemplatePreview(templateType) {
        const template = this.smsTemplates[templateType];
        const preview = document.getElementById('batchMessagePreview');
        
        if (preview && template) {
            preview.value = template.template;
        }
    }

    // 显示批量发送进度
    showBatchProgress(current, total) {
        const progressHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="batchProgressModal">
                <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 text-center">批量发送进度</h3>
                        
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>进度</span>
                                <span id="progressText">${current}/${total}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                     style="width: ${(current/total)*100}%"></div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="p-3 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600" id="successCount">0</div>
                                <div class="text-sm text-green-800">成功</div>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg">
                                <div class="text-lg font-bold text-red-600" id="failCount">0</div>
                                <div class="text-sm text-red-800">失败</div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <div class="text-sm text-gray-500">正在发送短信，请稍候...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = progressHtml;
    }

    // 更新批量发送进度
    updateBatchProgress(current, total, successCount, failCount) {
        const progressText = document.getElementById('progressText');
        const progressBar = document.getElementById('progressBar');
        const successCountEl = document.getElementById('successCount');
        const failCountEl = document.getElementById('failCount');
        
        if (progressText) progressText.textContent = `${current}/${total}`;
        if (progressBar) progressBar.style.width = `${(current/total)*100}%`;
        if (successCountEl) successCountEl.textContent = successCount;
        if (failCountEl) failCountEl.textContent = failCount;
    }

    // 隐藏批量发送进度
    hideBatchProgress() {
        const modal = document.getElementById('batchProgressModal');
        if (modal) {
            modal.remove();
        }
    }

    // 显示批量发送结果
    showBatchResult(successCount, failCount, total) {
        const resultHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="batchResultModal">
                <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="text-center mb-4">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full ${
                                failCount === 0 ? 'bg-green-100' : 'bg-yellow-100'
                            } mb-4">
                                <i class="fas ${failCount === 0 ? 'fa-check text-green-600' : 'fa-exclamation-triangle text-yellow-600'} text-xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900">批量发送完成</h3>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 text-center mb-6">
                            <div class="p-3 bg-blue-50 rounded-lg">
                                <div class="text-lg font-bold text-blue-600">${total}</div>
                                <div class="text-sm text-blue-800">总数</div>
                            </div>
                            <div class="p-3 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600">${successCount}</div>
                                <div class="text-sm text-green-800">成功</div>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg">
                                <div class="text-lg font-bold text-red-600">${failCount}</div>
                                <div class="text-sm text-red-800">失败</div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button onclick="document.getElementById('batchResultModal').remove()" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                确定
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = resultHtml;
    }

    // 添加到发送历史
    addToHistory(record) {
        this.sendHistory.unshift(record);
        
        // 只保留最近100条记录
        if (this.sendHistory.length > 100) {
            this.sendHistory = this.sendHistory.slice(0, 100);
        }
        
        // 保存到本地存储
        try {
            localStorage.setItem('sms_history', JSON.stringify(this.sendHistory));
        } catch (error) {
            console.warn('保存短信历史失败:', error);
        }
    }

    // 从本地存储加载历史
    loadHistory() {
        try {
            const history = localStorage.getItem('sms_history');
            if (history) {
                this.sendHistory = JSON.parse(history);
            }
        } catch (error) {
            console.warn('加载短信历史失败:', error);
            this.sendHistory = [];
        }
    }

    // 显示发送历史
    showSMSHistory() {
        this.loadHistory();
        
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="smsHistoryModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">短信发送历史</h3>
                            <button onclick="document.getElementById('smsHistoryModal').remove()" 
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">手机号</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">模板类型</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">发送时间</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${this.sendHistory.map(record => `
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                ${record.order_number}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${record.customer_name}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${record.phone}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${this.smsTemplates[record.template_type]?.name || record.template_type}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                ${new Date(record.sent_at).toLocaleString()}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    record.status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                }">
                                                    ${record.status === 'sent' ? '已发送' : '发送失败'}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="app.smsNotification.showMessageDetail('${record.order_id}', \`${record.message.replace(/`/g, '\\`')}\`)" 
                                                        class="text-blue-600 hover:text-blue-900" title="查看消息">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        
                        ${this.sendHistory.length === 0 ? `
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">暂无发送记录</p>
                        </div>
                        ` : ''}
                        
                        <div class="mt-6 flex justify-end">
                            <button onclick="document.getElementById('smsHistoryModal').remove()" 
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

    // 显示消息详情
    showMessageDetail(orderId, message) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="messageDetailModal">
                <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">短信内容</h3>
                            <button onclick="document.getElementById('messageDetailModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">消息内容</label>
                            <div class="p-3 bg-gray-50 rounded-md border">
                                <p class="text-sm text-gray-800 whitespace-pre-wrap">${message}</p>
                            </div>
                            <div class="text-right text-xs text-gray-500 mt-1">
                                字符数：${message.length}
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button onclick="document.getElementById('messageDetailModal').remove()" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                关闭
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
    }

    // 获取短信统计
    getSMSStats() {
        const today = new Date().toDateString();
        const todayRecords = this.sendHistory.filter(record => 
            new Date(record.sent_at).toDateString() === today
        );
        
        return {
            total: this.sendHistory.length,
            today: todayRecords.length,
            success: this.sendHistory.filter(r => r.status === 'sent').length,
            failed: this.sendHistory.filter(r => r.status === 'failed').length,
            todaySuccess: todayRecords.filter(r => r.status === 'sent').length,
            todayFailed: todayRecords.filter(r => r.status === 'failed').length
        };
    }

    // 清空发送历史
    clearHistory() {
        if (confirm('确定要清空所有短信发送历史吗？此操作不可恢复。')) {
            this.sendHistory = [];
            localStorage.removeItem('sms_history');
            this.app.showNotification('短信历史已清空', 'success');
        }
    }

    // 导出发送历史
    exportHistory() {
        if (this.sendHistory.length === 0) {
            this.app.showNotification('没有可导出的记录', 'info');
            return;
        }

        const csvContent = this.generateHistoryCSV();
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `sms_history_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.app.showNotification('短信历史导出成功', 'success');
    }

    // 生成历史CSV内容
    generateHistoryCSV() {
        const headers = ['订单号', '客户姓名', '手机号', '模板类型', '消息内容', '发送状态', '发送时间'];
        const rows = this.sendHistory.map(record => [
            record.order_number,
            record.customer_name,
            record.phone,
            this.smsTemplates[record.template_type]?.name || record.template_type,
            `"${record.message.replace(/"/g, '""')}"`, // CSV转义双引号
            record.status === 'sent' ? '已发送' : '发送失败',
            new Date(record.sent_at).toLocaleString()
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }

    // 延迟函数
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // 验证手机号格式
    validatePhone(phone) {
        const phoneRegex = /^1[3-9]\d{9}$/;
        return phoneRegex.test(phone);
    }

    // 获取短信模板列表
    getTemplates() {
        return this.smsTemplates;
    }

    // 添加自定义模板
    addCustomTemplate(key, name, template, variables = []) {
        this.smsTemplates[key] = {
            name: name,
            template: template,
            variables: variables
        };
        
        // 保存到本地存储
        try {
            const customTemplates = JSON.parse(localStorage.getItem('custom_sms_templates') || '{}');
            customTemplates[key] = this.smsTemplates[key];
            localStorage.setItem('custom_sms_templates', JSON.stringify(customTemplates));
        } catch (error) {
            console.warn('保存自定义模板失败:', error);
        }
    }

    // 加载自定义模板
    loadCustomTemplates() {
        try {
            const customTemplates = JSON.parse(localStorage.getItem('custom_sms_templates') || '{}');
            Object.assign(this.smsTemplates, customTemplates);
        } catch (error) {
            console.warn('加载自定义模板失败:', error);
        }
    }

    // 删除自定义模板
    deleteCustomTemplate(key) {
        if (this.smsTemplates[key] && !['repair_completed', 'repair_started', 'repair_delayed', 'custom'].includes(key)) {
            delete this.smsTemplates[key];
            
            // 从本地存储删除
            try {
                const customTemplates = JSON.parse(localStorage.getItem('custom_sms_templates') || '{}');
                delete customTemplates[key];
                localStorage.setItem('custom_sms_templates', JSON.stringify(customTemplates));
            } catch (error) {
                console.warn('删除自定义模板失败:', error);
            }
            
            return true;
        }
        return false;
    }

    // 初始化
    init() {
        console.log('初始化短信通知管理器...');
        // 等待DOM元素渲染完成后再初始化
        setTimeout(() => {
            this.loadHistory();
        }, 100);
        this.loadCustomTemplates();
    }
}
