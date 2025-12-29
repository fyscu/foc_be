// 页面内容管理
class PageManager {
    // 获取页面内容的主方法
    static getPageContent(pageId) {
        switch(pageId) {
            case 'dashboard':
                return this.getDashboardPage();
            case 'order-entry':
                return this.getOrderEntryPage();
            case 'order-management':
                return this.getOrderManagementPage();
            case 'technician-screen':
                return this.getTechnicianScreenPage();
            case 'service-screen':
                return this.getServiceScreenPage();
            case 'technician-management':
                return this.getTechnicianManagementPage();
            case 'activity-management':
                return this.getActivityManagementPage();
            default:
                return this.getDashboardPage();
        }
    }

    // 仪表板页面
    static getDashboardPage() {
        return `
            <div class="page-content" id="dashboard">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">概览</h2>
                
                <!-- 统计卡片 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-clipboard-list text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">总订单数</p>
                                <p class="text-2xl font-bold text-gray-900" id="totalOrders">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">待处理</p>
                                <p class="text-2xl font-bold text-gray-900" id="pendingOrders">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">已完成</p>
                                <p class="text-2xl font-bold text-gray-900" id="completedOrders">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">在线技术员</p>
                                <p class="text-2xl font-bold text-gray-900" id="onlineTechnicians">0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 快速操作 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">快速操作</h3>
                        <div class="space-y-3">
                            <button onclick="app.loadPage('order-entry')" 
                                    class="w-full text-left px-4 py-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                <i class="fas fa-plus text-blue-600 mr-3"></i>
                                <span class="text-blue-800">新建维修订单</span>
                            </button>
                            <button onclick="app.loadPage('technician-screen')" 
                                    class="w-full text-left px-4 py-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                                <i class="fas fa-desktop text-green-600 mr-3"></i>
                                <span class="text-green-800">6号位技术员大屏</span>
                            </button>
                            <button onclick="app.loadPage('service-screen')" 
                                    class="w-full text-left px-4 py-3 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                                <i class="fas fa-headset text-orange-600 mr-3"></i>
                                <span class="text-orange-800">5号位客服大屏</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">信息概览</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">当前活动</span>
                                <span class="text-gray-800" id="currentActivity">暂无活动</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">活动日期</span>
                                <span class="text-gray-800" id="activityDate">暂无活动</span>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- 最近订单 -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">最近订单</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody id="recentOrdersList" class="bg-white divide-y divide-gray-200">
                                <!-- 最近订单列表将在这里动态加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    // 订单录入页面
    static getOrderEntryPage() {
        return `
            <div class="page-content" id="order-entry">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">订单录入</h2>
                
                <!-- 编号显示区域 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">1号位 (单号)</h3>
                        <p class="text-sm text-gray-600 mb-2">负责录入：0001, 0003, 0005...</p>
                        <div class="text-2xl font-bold text-blue-600" id="nextOddNumber">0001</div>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-800 mb-2">4号位 (双号)</h3>
                        <p class="text-sm text-gray-600 mb-2">负责录入：0002, 0004, 0006...</p>
                        <div class="text-2xl font-bold text-green-600" id="nextEvenNumber">0002</div>
                    </div>
                </div>

                <!-- 表单区域 -->
                <form id="orderForm" class="space-y-6">
                    <!-- 表单头部信息 -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">录入位置</label>
                                <select name="position" id="position" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">请选择录入位置</option>
                                    <option value="1">1号位 (单号)</option>
                                    <option value="2">4号位 (双号)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">维修编号</label>
                                <input type="text" name="order_number" id="orderNumber" readonly 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- 机主个人基本信息 -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">机主个人基本信息</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">姓名</label>
                                <input type="text" name="customer_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">性别</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="gender" value="男" class="form-radio">
                                        <span class="ml-2">男</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="gender" value="女" class="form-radio">
                                        <span class="ml-2">女</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">手机号</label>
                                <input type="tel" name="customer_phone" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">备用联系人手机号</label>
                                <input type="tel" name="backup_phone" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">就读学院</label>
                                <input type="text" name="college" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">居住宿舍</label>
                                <input type="text" name="dormitory" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">学号</label>
                                <input type="text" name="student_id" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">QQ号码</label>
                                <input type="text" name="customer_qq" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- 待修电脑基本信息 -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">待修电脑基本信息</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">设备类型</label>
                                <select name="device_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">请选择设备类型</option>
                                    <option value="台式机">台式机</option>
                                    <option value="笔记本">笔记本</option>
                                    <option value="一体机">一体机</option>
                                    <option value="其他">其他</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">品牌与型号</label>
                                <input type="text" name="device_model" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">开机密码</label>
                                <input type="text" name="login_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">建议您维修后更改密码</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">外带附件</label>
                                <textarea name="accessories" rows="2" maxlength="200"
                                          placeholder="如：电源线、鼠标、键盘等"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">电脑已经存在的外观及硬件损坏与缺陷</label>
                                <textarea name="existing_damage" rows="2" maxlength="200"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 所需服务 -->
                    <div class="space-y-4 mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">所需服务</h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="硬件初级检修" class="form-checkbox">
                                <span class="ml-2">硬件初级检修</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="拆机清洁" class="form-checkbox">
                                <span class="ml-2">拆机清洁</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="数据恢复" class="form-checkbox">
                                <span class="ml-2">数据恢复</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="软件、驱动安装" class="form-checkbox">
                                <span class="ml-2">软件、驱动安装</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="病毒清除、系统修复" class="form-checkbox">
                                <span class="ml-2">病毒清除、系统修复</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="service_type[]" value="系统优化" class="form-checkbox">
                                <span class="ml-2">系统优化</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">若维修进行中有必要是否可以采用系统重装</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="allow_system_reinstall" value="是" class="form-radio">
                                        <span class="ml-2">是</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="allow_system_reinstall" value="否" class="form-radio">
                                        <span class="ml-2">否</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">在进行系统重装操作时是否可以清空硬盘</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="allow_disk_format" value="是" class="form-radio">
                                        <span class="ml-2">是</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="allow_disk_format" value="否" class="form-radio">
                                        <span class="ml-2">否</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">故障描述 <span class="text-red-500">*</span></label>
                            <textarea name="problem_description" rows="3" required maxlength="500"
                                      placeholder="请详细描述设备故障现象..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">需要进行备份的重要数据</label>
                            <textarea name="important_data" rows="2" maxlength="200"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">对于故障或维修要求的补充描述</label>
                            <textarea name="repair_notes" rows="2" maxlength="200"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>

                    <!-- 备注 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">备注</label>
                        <textarea name="notes" rows="2" maxlength="200"
                                  placeholder="其他需要说明的情况..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- 提交按钮 -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" id="resetForm" 
                                class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            重置
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            提交订单
                        </button>
                        <button type="button" id="submitAndNext" 
                                class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            提交并添加下一个
                        </button>
                    </div>
                </form>
            </div>
        `;
    }

    // 订单管理页面
    static getOrderManagementPage() {
        return `
            <div class="page-content" id="order-management">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">订单管理</h2>
                
                <!-- 筛选器 -->
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">状态筛选</label>
                            <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">全部状态</option>
                                <option value="pending">待接单</option>
                                <option value="processing">维修中</option>
                                <option value="ready">待取机</option>
                                <option value="completed">已完成</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">搜索订单号</label>
                            <input type="text" id="orderSearch" placeholder="输入订单号"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">客户姓名</label>
                            <input type="text" id="customerSearch" placeholder="输入客户姓名"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div class="flex items-end">
                            <button id="searchBtn" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                搜索
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 订单列表 -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    订单号
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    客户信息
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    设备类型
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    设备型号
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    问题描述
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    状态
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    技术员
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        <tbody id="orderList" class="bg-white divide-y divide-gray-200">
                            <!-- 订单列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // 6号位技术员大屏
    static getTechnicianScreenPage() {
        return `
            <div class="page-content" id="technician-screen">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">6号位 - 大屏</h2>
                
                <!-- 状态统计 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-yellow-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-yellow-800">待接单</h3>
                        <p class="text-3xl font-bold text-yellow-600" id="techPendingCount">0</p>
                    </div>
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800">维修中</h3>
                        <p class="text-3xl font-bold text-blue-600" id="techProcessingCount">0</p>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-800">在线技术员</h3>
                        <p class="text-3xl font-bold text-green-600" id="onlineTechCount">0</p>
                    </div>
                </div>

                <!-- 订单列表 -->
                <table class="min-w-full divide-y divide-gray-200">
                            
                            <tbody id="techOrderList" class="bg-white divide-y divide-gray-200">
                                <!-- 订单列表将在这里动态加载 -->
                            </tbody>
                        </table>

            </div>
        `;
    }

    // 5号位客服大屏
    static getServiceScreenPage() {
        return `
            <div class="page-content" id="service-screen">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">5号位 - 大屏</h2>
                
                <!-- 状态统计 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-orange-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-orange-800">待取机</h3>
                        <p class="text-3xl font-bold text-orange-600" id="serviceReadyCount">0</p>
                    </div>
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-800">本次大修完成</h3>
                        <p class="text-3xl font-bold text-green-600" id="todayCompletedCount">0</p>
                    </div>
                </div>

                <!-- 待取机订单列表 -->
                
                    <div id="serviceOrderList" class="divide-y divide-gray-200">
                        <!-- 订单列表将在这里动态加载 -->
                    </div>
                
            </div>
        `;
    }

    // 技术员管理页面
    static getTechnicianManagementPage() {
        return `
            <div class="page-content" id="technician-management">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">技术员管理</h2>
                
                <!-- 添加技术员按钮 -->
                <div class="mb-6">
                    <button id="addTechnicianBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>添加技术员
                    </button>
                </div>

                <!-- 技术员列表 -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">姓名</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">联系电话</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">专业领域</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">当前订单数</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                            </tr>
                        </thead>
                        <tbody id="technicianList" class="bg-white divide-y divide-gray-200">
                            <!-- 技术员列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // 活动管理页面
    static getActivityManagementPage() {
        return `
            <div class="page-content" id="activity-management">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">活动管理</h2>
                
                <!-- 当前活动信息 -->
                <div class="bg-blue-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-semibold text-blue-800 mb-2">当前活动</h3>
                    <div id="currentActivityInfo">
                        <p class="text-gray-600">暂无活动</p>
                    </div>
                </div>

                <!-- 创建新活动 -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">创建新活动</h3>
                    <form id="activityForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">活动名称</label>
                                <input type="text" name="activity_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">活动日期</label>
                                <input type="date" name="activity_date" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">活动描述</label>
                            <textarea name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                创建活动
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 活动列表 -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">历史活动</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">活动名称</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">日期</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">订单数量</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                            </tr>
                        </thead>
                        <tbody id="activityList" class="bg-white divide-y divide-gray-200">
                            <!-- 活动列表将在这里动态加载 -->
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
}
