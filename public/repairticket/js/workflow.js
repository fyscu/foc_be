// 工作流状态管理
class WorkflowManager {
    constructor(app) {
        this.app = app;
        this.statusConfig = {
            'pending': {
                name: '待接单',
                color: 'gray',
                next: ['processing'],
                actions: ['assign_technician']
            },
            'processing': {
                name: '维修中',
                color: 'blue',
                next: ['ready'],
                actions: ['complete_repair', 'transfer_order']
            },
            'ready': {
                name: '待取机',
                color: 'orange',
                next: ['completed'],
                actions: ['send_sms', 'complete_order']
            },
            'completed': {
                name: '已完成',
                color: 'green',
                next: [],
                actions: []
            }
        };
    }

    // 获取状态配置
    getStatusConfig(status) {
        return this.statusConfig[status] || null;
    }

    // 获取状态显示名称
    getStatusName(status) {
        const config = this.getStatusConfig(status);
        return config ? config.name : status;
    }

    // 获取状态颜色
    getStatusColor(status) {
        const config = this.getStatusConfig(status);
        return config ? config.color : 'gray';
    }

    // 检查状态转换是否有效
    canTransitionTo(currentStatus, targetStatus) {
        const config = this.getStatusConfig(currentStatus);
        return config && config.next.includes(targetStatus);
    }

    // 获取可用的操作
    getAvailableActions(status) {
        const config = this.getStatusConfig(status);
        return config ? config.actions : [];
    }

    // 更新订单状态
    async updateOrderStatus(orderId, newStatus, notes = '') {
        try {
            // 先获取当前订单信息
            const orderResult = await API.getOrderById(orderId);
            if (!orderResult.success) {
                throw new Error('获取订单信息失败');
            }

            const currentStatus = orderResult.data.status;
            
            // 检查状态转换是否有效
            if (!this.canTransitionTo(currentStatus, newStatus)) {
                throw new Error(`无法从 ${this.getStatusName(currentStatus)} 转换到 ${this.getStatusName(newStatus)}`);
            }

            // 更新状态
            const result = await API.updateOrderStatus(orderId, newStatus);
            if (result.success) {
                this.app.showNotification(
                    `订单状态已更新为：${this.getStatusName(newStatus)}`, 
                    'success'
                );
                
                // 记录操作日志
                await this.logStatusChange(orderId, currentStatus, newStatus, notes);
                
                // 刷新相关界面
                this.refreshRelatedViews();
                
                return true;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('更新订单状态失败:', error);
            this.app.showNotification('状态更新失败：' + error.message, 'error');
            return false;
        }
    }

    // 记录状态变更日志
    async logStatusChange(orderId, oldStatus, newStatus, notes) {
        try {
            await API.logOrderAction(orderId, 'status_change', {
                old_status: oldStatus,
                new_status: newStatus,
                notes: notes
            });
        } catch (error) {
            console.error('记录状态变更日志失败:', error);
        }
    }

    // 批量更新订单状态
    async batchUpdateStatus(orderIds, newStatus) {
        const results = [];
        for (const orderId of orderIds) {
            const result = await this.updateOrderStatus(orderId, newStatus);
            results.push({ orderId, success: result });
        }
        
        const successCount = results.filter(r => r.success).length;
        const totalCount = results.length;
        
        if (successCount === totalCount) {
            this.app.showNotification(`成功更新 ${successCount} 个订单状态`, 'success');
        } else {
            this.app.showNotification(
                `更新完成：成功 ${successCount} 个，失败 ${totalCount - successCount} 个`, 
                'warning'
            );
        }
        
        return results;
    }

    // 显示状态转换确认对话框
    showStatusTransitionDialog(orderId, currentStatus, targetStatus) {
        const currentName = this.getStatusName(currentStatus);
        const targetName = this.getStatusName(targetStatus);
        
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="statusModal">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">确认状态变更</h3>
                        <p class="text-gray-600 mb-4">
                            将订单状态从 <span class="font-semibold text-${this.getStatusColor(currentStatus)}-600">${currentName}</span> 
                            更改为 <span class="font-semibold text-${this.getStatusColor(targetStatus)}-600">${targetName}</span>
                        </p>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">备注（可选）</label>
                            <textarea id="statusNotes" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="请输入状态变更的备注信息..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button onclick="document.getElementById('statusModal').remove()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                取消
                            </button>
                            <button onclick="app.workflow.confirmStatusChange('${orderId}', '${targetStatus}')" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                确认更改
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modalContainer').innerHTML = modalHtml;
    }

    // 确认状态变更
    async confirmStatusChange(orderId, newStatus) {
        const notes = document.getElementById('statusNotes').value;
        const success = await this.updateOrderStatus(orderId, newStatus, notes);
        
        if (success) {
            document.getElementById('statusModal').remove();
        }
    }

    // 显示订单状态历史
    async showStatusHistory(orderId) {
        try {
            const history = await API.getOrderHistory(orderId);
            if (history.success) {
                this.renderStatusHistoryModal(history.data);
            }
        } catch (error) {
            console.error('获取状态历史失败:', error);
            this.app.showNotification('获取状态历史失败', 'error');
        }
    }

    // 渲染状态历史模态框
    renderStatusHistoryModal(historyData) {
        const modalHtml = `
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="historyModal">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">订单状态历史</h3>
                            <button onclick="document.getElementById('historyModal').remove()" 
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            ${historyData.map(item => `
                                <div class="border-l-4 border-${this.getStatusColor(item.new_status)}-400 pl-4 py-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-gray-900">
                                                ${item.action === 'status_change' ? '状态变更' : item.action}
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                ${item.old_status ? `${this.getStatusName(item.old_status)} → ` : ''}
                                                <span class="text-${this.getStatusColor(item.new_status)}-600 font-medium">
                                                    ${this.getStatusName(item.new_status)}
                                                </span>
                                            </p>
                                            ${item.notes ? `<p class="text-sm text-gray-500 mt-1">${item.notes}</p>` : ''}
                                        </div>
                                        <span class="text-xs text-gray-400">${item.created_at}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button onclick="document.getElementById('historyModal').remove()" 
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

    // 刷新相关视图
    refreshRelatedViews() {
        // 更新控制台统计
        this.app.updateDashboard();
        
        // 根据当前页面刷新对应视图
        switch (this.app.currentPage) {
            case 'order-management':
                this.app.loadOrders();
                break;
            case 'technician-screen':
                this.app.screenDisplay.loadTechnicianScreenData();
                break;
            case 'service-screen':
                this.app.screenDisplay.loadServiceScreenData();
                break;
        }
    }

    // 获取状态统计
    async getStatusStatistics() {
        try {
            const stats = await API.getOrderStats();
            return stats.success ? stats.data : null;
        } catch (error) {
            console.error('获取状态统计失败:', error);
            return null;
        }
    }

    // 渲染状态徽章
    renderStatusBadge(status, className = '') {
        const config = this.getStatusConfig(status);
        const colorClass = `status-${status}`;
        return `<span class="status-badge ${colorClass} ${className}">${config.name}</span>`;
    }
}