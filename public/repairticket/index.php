<?php 
include 'auth_check.php'; 
$adminTag = $_GET['admin'] ?? 'false';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>大修工单管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src='https://lf-package-cn.feishucdn.com/obj/feishu-static/op/fe/devtools_frontend/remote-debug-0.0.1-alpha.6.js'></script>
    <link href="css/screen-styles.css" rel="stylesheet">
    <style>
        /* 自定义样式 */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        .status-badge {
            @apply px-2 py-1 rounded-full text-xs font-medium;
        }
        .status-pending { @apply bg-gray-100 text-gray-800; }
        .status-processing { @apply bg-blue-100 text-blue-800; }
        .status-ready { @apply bg-orange-100 text-orange-800; }
        .status-completed { @apply bg-green-100 text-green-800; }
        .nav-item * {
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 顶部导航栏 -->
    <nav class="bg-blue-600 text-white shadow-lg">
        <div class="px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <button id="mobileMenuBtn" class="md:hidden px-3 py-2 rounded hover:bg-blue-600/20">
  <i class="fas fa-bars"></i>
</button>
                <h1 class="text-xl font-bold">飞扬俱乐部大修工单管理系统</h1>
            </div>
            <!-- <div class="flex items-center space-x-4">
                <span id="currentUser" class="text-sm">管理员</span>
                <button id="logoutBtn" class="bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>退出
                </button>
            </div> -->
        </div>
    </nav>

    <div class="flex flex-col md:flex-row">
        <!-- 侧边导航菜单 -->
        <aside
  id="sidebar"
  class="hidden md:block w-64 min-h-screen bg-white shadow-lg sidebar-transition
         md:static md:translate-x-0
         fixed top-0 left-0 z-50 translate-x-[-100%]">
            <div class="p-4">
                <nav class="space-y-2">
                    
                    <a href="#" data-page="dashboard" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>控制台</span>
                    </a>
                    <a href="#" data-page="order-entry" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-plus-circle"></i>
                        <span>订单录入</span>
                    </a>
                    
                    <a href="#" data-page="technician-screen" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-desktop"></i>
                        <span>6号位大屏</span>
                    </a>
                    <a href="#" data-page="service-screen" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-tv"></i>
                        <span>5号位大屏</span>
                    </a>
                    <?php if($adminTag == 'yes'){ ?>
                    <a href="#" data-page="order-management" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-list-alt"></i>
                        <span>订单管理</span>
                    </a>
                    <a href="#" data-page="technician-management" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-users"></i>
                        <span>技术员管理</span>
                    </a>
                    <a href="#" data-page="activity-management" class="nav-item flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-50 text-gray-700 hover:text-blue-600">
                        <i class="fas fa-calendar-alt"></i>
                        <span>活动管理</span>
                    </a>
                    <?php } ?>
                </nav>
            </div>
        </aside>
        <div id="sidebarMask" class="hidden fixed inset-0 bg-black/30 z-40 md:hidden"></div>

        <!-- 主内容区域 -->
        <main class="flex-1 p-4 md:p-6">
  <div class="max-w-screen-xl mx-auto">
    <div id="pageContent" class="bg-white rounded-lg card-shadow p-4 md:p-6">

                <!-- 页面内容将在这里动态加载 -->
                <div id="dashboard" class="page-content">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">控制台</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-blue-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-500 rounded-full">
                                    <i class="fas fa-clipboard-list text-white"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-800">待接单</h3>
                                    <p class="text-2xl font-bold text-blue-600" id="pendingCount">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-orange-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 bg-orange-500 rounded-full">
                                    <i class="fas fa-tools text-white"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-800">维修中</h3>
                                    <p class="text-2xl font-bold text-orange-600" id="processingCount">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-yellow-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 bg-yellow-500 rounded-full">
                                    <i class="fas fa-bell text-white"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-800">待取机</h3>
                                    <p class="text-2xl font-bold text-yellow-600" id="readyCount">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-green-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-3 bg-green-500 rounded-full">
                                    <i class="fas fa-check-circle text-white"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-800">已完成</h3>
                                    <p class="text-2xl font-bold text-green-600" id="completedCount">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>
        </main>
    </div>

    <!-- 模态框容器 -->
    <div id="modalContainer"></div>

    <!-- JavaScript -->
    <script src="js/api.js"></script>
    <script src="js/pages.js"></script>
    <script src="js/order-entry.js"></script>
    <script src="js/workflow.js"></script>
    <script src="js/screen-display.js"></script>
    <script src="js/technician-management.js"></script>
    <script src="js/sms-notification.js"></script>
    <script src="js/app.js"></script>
    <script src="js/qr.js"></script>
    <script>
  (function () {
    const btn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const mask = document.getElementById('sidebarMask');

    function openSidebar() {
      sidebar.classList.remove('hidden');
      requestAnimationFrame(() => { // 等下一帧做动画
        sidebar.classList.remove('translate-x-[-100%]');
        mask.classList.remove('hidden');
      });
    }
    function closeSidebar() {
      sidebar.classList.add('translate-x-[-100%]');
      mask.classList.add('hidden');
      // 结束动画后隐藏节点，避免挡点击
      setTimeout(() => {
        if (getComputedStyle(sidebar).transform.includes('-100')) sidebar.classList.add('hidden');
      }, 300);
    }

    btn && btn.addEventListener('click', openSidebar);
    mask && mask.addEventListener('click', closeSidebar);

    // 切换页面时也自动关闭抽屉（你是通过 data-page 切内容）
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a.nav-item');
      if (a && window.innerWidth < 768) closeSidebar();
    });
  })();
</script>

</body>
</html>
