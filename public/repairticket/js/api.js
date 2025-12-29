// API 接口管理
class API {
    static baseURL = 'https://focapp.feiyang.ac.cn/public/repairticket/api';

    // 通用请求方法
    static async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        };

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API请求失败:', error);
            throw error;
        }
    }

    // GET 请求
    static async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url);
    }

    // POST 请求
    static async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    // PUT 请求
    static async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    // DELETE 请求
    static async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE',
        });
    }

    // 订单相关API
    static async getOrders(params = {}) {
        return this.get('get_orders.php', params);
    }

    static async getOrderById(orderId) {
        return this.get('get_order_by_id.php', { id: orderId });
    }

    static async createOrder(orderData) {
        return this.post('create_order.php', orderData);
    }

    static async updateOrderStatus(orderId, status) {
        return this.put('update_order_status.php', { id: orderId, status });
    }

    static async getOrderStats() {
        return this.get('get_order_stats.php');
    }

    static async getNextOrderNumber(position) {
        return this.get('get_next_order_number.php', { position });
    }

    // 技术员相关API
    static async getTechnicians(params = {}) {
        return this.get('get_technicians.php', params);
    }
    
    static async getTechnicianUsers() {
        return this.get('get_technician_users.php');
    }
    
    static async importTechnicians(users) {
        return this.post('import_technicians.php', { users });
    }

    static async getTechnicianById(technicianId) {
        return this.get('get_technician_by_id.php', { id: technicianId });
    }

    static async createTechnician(technicianData) {
        return this.post('create_technician.php', technicianData);
    }

    static async updateTechnician(technicianId, technicianData) {
        return this.post('update_technician.php', { ...technicianData, id: technicianId });
    }

    static async updateTechnicianStatus(technicianId, status) {
        return this.post('update_technician_status.php', {
            technician_id: technicianId,
            status: status
        });
    }

    static async getTechnicianOrders(technicianId) {
        return this.get('get_technician_orders.php', { technician_id: technicianId });
    }

    static async assignTechnician(orderId, technicianId) {
        return this.post('assign_technician.php', {
            order_id: orderId,
            technician_id: technicianId
        });
    }

    static async transferOrder(orderId, fromTechnicianId, toTechnicianId, reason = '') {
        return this.post('transfer_order.php', {
            order_id: orderId,
            from_technician_id: fromTechnicianId,
            to_technician_id: toTechnicianId,
            reason: reason
        });
    }

    // 短信相关API
    static async sendSMS(smsData) {
        return this.post('send_sms.php', smsData);
    }

    // 标记短信已发送
    static async markSMSSent(orderId) {
        return this.post('mark_sms_sent.php', { order_id: orderId });
    }

    static async getSMSLogs(orderId = null) {
        const params = orderId ? { order_id: orderId } : {};
        return this.get('get_sms_logs.php', params);
    }

    static async getSMSStats() {
        return this.get('get_sms_stats.php');
    }

    // 活动相关API
    static async getActivities() {
        return this.get('get_activities.php');
    }

    static async createActivity(activityData) {
        return this.post('create_activity.php', activityData);
    }

    static async setCurrentActivity(activityId) {
        return this.post('set_current_activity.php', { activity_id: activityId });
    }

    // 根据ID获取活动详情
    static async getActivityById(activityId) {
        return this.get(`get_activity_by_id.php?id=${activityId}`);
    }

    // 工作流相关API
    static async logOrderAction(orderId, action, data = {}) {
        return this.post('log_order_action.php', {
            order_id: orderId,
            action: action,
            ...data
        });
    }

    static async getOrderHistory(orderId) {
        return this.get('get_order_history.php', { order_id: orderId });
    }

    static async transferOrder(orderId, fromTechnicianId, toTechnicianId) {
        return this.post('transfer_order.php', {
            order_id: orderId,
            from_technician_id: fromTechnicianId,
            to_technician_id: toTechnicianId
        });
    }
}