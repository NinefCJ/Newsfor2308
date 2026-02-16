<?php
// includes/header.php - 简化修复版本

// 确保只初始化一次
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置一个标志防止重复包含
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
    
    // 包含必要的文件
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
    
    // 查询分类数据 - 只执行一次
    if (!isset($GLOBALS['nav_categories'])) {
        $GLOBALS['nav_categories'] = [];
        
        // 使用 DISTINCT 和 GROUP BY 确保唯一性
        $sql = "SELECT id, name FROM categories GROUP BY name ORDER BY id ASC";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $GLOBALS['nav_categories'][$row['id']] = $row;
            }
            $result->free();
        }
    }
} else {
    // 如果已经包含过，直接返回
    return;
}

// 获取当前页面
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>2308班新闻中心</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- 自定义样式 -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        /* 临时样式，帮助调试 */
        .debug-info {
            display: none; /* 生产环境隐藏 */
            background: #f0f0f0;
            padding: 5px;
            border-left: 3px solid red;
            margin-bottom: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- 调试信息 -->
    <div class="debug-info">
        当前页面: <?php echo $current_page; ?> | 
        分类数量: <?php echo isset($GLOBALS['nav_categories']) ? count($GLOBALS['nav_categories']) : 0; ?>
    </div>
    
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <!-- 品牌标志 -->
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-newspaper me-2"></i>
                <span class="fw-bold">2308新闻</span>
            </a>
            
            <!-- 移动端菜单按钮 -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- 导航菜单 -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto">
                    <!-- 首页 -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" 
                           href="index.php">
                            <i class="bi bi-house"></i> 首页
                        </a>
                    </li>
                    
                    <!-- 分类菜单 - 关键修复部分 -->
                    <?php
                    if (isset($GLOBALS['nav_categories']) && !empty($GLOBALS['nav_categories'])) {
                        // 确保没有重复ID
                        $displayed_ids = [];
                        
                        foreach ($GLOBALS['nav_categories'] as $id => $category) {
                            // 检查是否已经显示过这个ID
                            if (in_array($id, $displayed_ids)) {
                                continue;
                            }
                            $displayed_ids[] = $id;
                            
                            // 判断是否激活
                            $is_active = false;
                            if ($current_page == 'category.php' && isset($_GET['id']) && $_GET['id'] == $id) {
                                $is_active = true;
                            }
                            
                            $active_class = $is_active ? 'active' : '';
                            ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_class; ?>" 
                                   href="category.php?id=<?php echo $id; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                            <?php
                        }
                    } else {
                        // 备用：显示默认分类
                        $default_categories = [
                            ['id' => 1, 'name' => '班级动态'],
                            ['id' => 2, 'name' => '校园热点'],
                            ['id' => 3, 'name' => '通知公告'],
                            ['id' => 4, 'name' => '学习心得'],
                            ['id' => 5, 'name' => '比赛荣誉']
                        ];
                        
                        foreach ($default_categories as $category) {
                            echo '<li class="nav-item">';
                            echo '<a class="nav-link" href="category.php?id=' . $category['id'] . '">';
                            echo htmlspecialchars($category['name']);
                            echo '</a>';
                            echo '</li>';
                        }
                    }
                    ?>
                    
                    <!-- 关于我们 -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" 
                           href="about.php">
                            <i class="bi bi-info-circle"></i> 关于我们
                        </a>
                    </li>
                </ul>
                
                <!-- 右侧功能 -->
                <div class="navbar-nav">
                    <a href="admin/login.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-lock"></i> 管理登录
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- 主内容区开始 -->
    <main class="container my-4">
