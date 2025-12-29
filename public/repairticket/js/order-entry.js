// 订单录入功能扩展
class OrderEntry {
    constructor(app) {
        this.app = app;
    }

    // 初始化订单录入页面
    init() {
        console.log('初始化订单录入页面...');
        
        // 等待DOM元素渲染完成后再初始化
        setTimeout(() => {
            // 绑定表单事件
            this.bindFormEvents();
            
            // 更新下一个编号显示
            this.updateNextNumbers();
            
            // 绑定快捷键
            this.bindKeyboardShortcuts();
            
            // 绑定字符计数
            this.bindCharacterCount();
        }, 100);
    }

    // 绑定表单事件
    bindFormEvents() {
        const form = document.getElementById('orderForm');
        const positionSelect = document.getElementById('position');
        const resetBtn = document.getElementById('resetForm');
        const submitNextBtn = document.getElementById('submitAndNext');

        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitOrder(form, false);
            });
        }

        if (positionSelect) {
            positionSelect.addEventListener('change', (e) => {
                this.updateOrderNumber(e.target.value);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                document.getElementById('orderNumber').value = '';
            });
        }

        if (submitNextBtn) {
            submitNextBtn.addEventListener('click', () => {
                this.submitOrder(form, true);
            });
        }

        // 自动保存草稿
        if (form) {
            form.addEventListener('input', () => {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                this.saveDraft(data);
            });
        }
    }

    // 完善的订单提交方法
    async submitOrder(form, addNext = false) {
        const formData = new FormData(form);
        const orderData = {};
        
        // 处理复选框的多选值
        const serviceTypes = [];
        formData.forEach((value, key) => {
            if (key === 'service_type[]') {
                serviceTypes.push(value);
            } else {
                orderData[key] = value;
            }
        });
        
        if (serviceTypes.length > 0) {
            orderData.service_type = serviceTypes;
        }
        
        // 移除不应在创建订单时填写的字段
        delete orderData.pickup_time;
        delete orderData.staff_signature;
        delete orderData.customer_signature;
        delete orderData.technician1_name;
        delete orderData.technician1_time;
        delete orderData.technician1_phone;
        delete orderData.diagnosis;
        delete orderData.solution;
        delete orderData.technician_signature;
        
        // 验证必填字段
        if (!orderData.position || !orderData.customer_name || !orderData.customer_phone || 
            !orderData.device_type || !orderData.problem_description) {
            this.app.showNotification('请填写所有必填字段', 'error');
            return;
        }

        // 验证手机号格式
        const phoneRegex = /^1[3-9]\d{9}$/;
        if (!phoneRegex.test(orderData.customer_phone)) {
            this.app.showNotification('请输入正确的手机号码', 'error');
            return;
        }
        
        // 验证备用手机号格式（如果填写）
        if (orderData.backup_phone && !phoneRegex.test(orderData.backup_phone)) {
            this.app.showNotification('请输入正确的备用联系人手机号码', 'error');
            return;
        }
        
        try {
            const result = await API.createOrder(orderData);
            if (result.success) {
                this.app.showNotification(`订单 ${result.data.order_number} 创建成功！`, 'success');
                
                if (addNext) {
                    // 保留位置选择，清空其他字段
                    const position = orderData.position;
                    form.reset();
                    document.getElementById('position').value = position;
                    await this.updateOrderNumber(position);
                    
                    // 聚焦到客户姓名字段
                    document.querySelector('input[name="customer_name"]').focus();
                } else {
                    form.reset();
                    document.getElementById('orderNumber').value = '';
                }
                
                // 更新控制台统计
                this.app.updateDashboard();
                // 更新下一个编号显示
                this.updateNextNumbers();
                
            } else {
                this.app.showNotification('订单创建失败：' + result.message, 'error');
            }
        } catch (error) {
            console.error('提交订单失败:', error);
            this.app.showNotification('提交订单失败，请重试', 'error');
        }
    }

    // 更新订单编号
    async updateOrderNumber(position) {
        try {
            const result = await API.getNextOrderNumber(position);
            if (result.success) {
                document.getElementById('orderNumber').value = result.data.order_number;
            }
        } catch (error) {
            console.error('获取订单编号失败:', error);
        }
    }

    // 更新下一个编号显示
    async updateNextNumbers() {
        try {
            // 检查DOM元素是否存在
            const nextOddElement = document.getElementById('nextOddNumber');
            const nextEvenElement = document.getElementById('nextEvenNumber');
            
            if (!nextOddElement || !nextEvenElement) {
                console.log('编号显示元素未找到，跳过更新');
                return;
            }

            // 获取1号位下一个编号
            const oddResult = await API.getNextOrderNumber('1');
            if (oddResult.success && nextOddElement) {
                nextOddElement.textContent = oddResult.data.order_number;
            }

            // 获取4号位下一个编号
            const evenResult = await API.getNextOrderNumber('2');
            if (evenResult.success && nextEvenElement) {
                nextEvenElement.textContent = evenResult.data.order_number;
            }
        } catch (error) {
            console.error('更新编号显示失败:', error);
        }
    }

    // 表单验证
    validateForm(formData) {
        const errors = [];

        // 客户姓名验证
        if (!formData.customer_name || formData.customer_name.trim().length < 2) {
            errors.push('客户姓名至少需要2个字符');
        }

        // 手机号验证
        const phoneRegex = /^1[3-9]\d{9}$/;
        if (!phoneRegex.test(formData.customer_phone)) {
            errors.push('请输入正确的手机号码');
        }

        // 备用手机号验证（如果填写）
        if (formData.backup_phone && !phoneRegex.test(formData.backup_phone)) {
            errors.push('备用联系人手机号格式不正确');
        }

        // QQ号验证（如果填写）
        if (formData.customer_qq && !/^\d{5,12}$/.test(formData.customer_qq)) {
            errors.push('QQ号格式不正确');
        }

        // 学号验证（如果填写）
        if (formData.student_id && formData.student_id.trim().length < 5) {
            errors.push('学号格式不正确');
        }

        // 故障描述验证
        if (!formData.problem_description || formData.problem_description.trim().length < 5) {
            errors.push('故障描述至少需要5个字符');
        }

        return errors;
    }

    // 自动保存草稿
    saveDraft(formData) {
        const draftKey = `order_draft_${formData.position || 'temp'}`;
        localStorage.setItem(draftKey, JSON.stringify(formData));
    }

    // 加载草稿
    loadDraft(position) {
        const draftKey = `order_draft_${position}`;
        const draft = localStorage.getItem(draftKey);
        if (draft) {
            try {
                return JSON.parse(draft);
            } catch (error) {
                console.error('加载草稿失败:', error);
            }
        }
        return null;
    }

    // 清除草稿
    clearDraft(position) {
        const draftKey = `order_draft_${position}`;
        localStorage.removeItem(draftKey);
    }

    // 快捷键支持
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Enter 快速提交
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('orderForm');
                if (form) {
                    this.submitOrder(form, false);
                }
            }
            
            // Ctrl+Shift+Enter 提交并添加下一个
            if (e.ctrlKey && e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('orderForm');
                if (form) {
                    this.submitOrder(form, true);
                }
            }
        });
    }

    // 实时字数统计
    bindCharacterCount() {
        const textareas = document.querySelectorAll('textarea');
        
        textareas.forEach(textarea => {
            if (textarea) {
                const maxLength = textarea.getAttribute('maxlength') || 500;
                const countElement = document.createElement('div');
                countElement.className = 'text-sm text-gray-500 mt-1 text-right';
                countElement.textContent = `0/${maxLength}`;
                textarea.parentNode.appendChild(countElement);

                textarea.addEventListener('input', () => {
                    const length = textarea.value.length;
                    countElement.textContent = `${length}/${maxLength}`;
                    countElement.className = length > maxLength * 0.9 
                        ? 'text-sm text-red-500 mt-1 text-right'
                        : 'text-sm text-gray-500 mt-1 text-right';
                });
            }
        });
    }
}