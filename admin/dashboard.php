<?php
/**
 * 后台管理主页 - 新闻文章列表
 */
require_once '../includes/config.php';
require_once 'includes/auth_check.php'; // 必须登录才能访问

// 处理可能的操作反馈消息（来自删除等操作）
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']); // 显示后清除

// 分页设置
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 15;
$offset = ($current_page - 1) * $items_per_page;

// 构建查询（支持按分类筛选和搜索，此处为基本列表）
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$sql_where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    $sql_where .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search_param = "%" . $search_keyword . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($category_filter > 0) {
    $sql_where .= " AND a.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

// 获取文章总数用于分页
$count_sql = "SELECT COUNT(*) as total FROM articles a $sql_where";
$stmt = $conn->prepare($count_sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);
$stmt->close();

// 获取文章列表数据
$sql = "SELECT a.id, a.title, a.publish_date, a.view_count, a.is_featured, c.name as category_name 
        FROM articles a 
        LEFT JOIN categories c ON a.category_id = c.id 
        $sql_where 
        ORDER BY a.is_featured DESC, a.publish_date DESC 
        LIMIT ? OFFSET ?";

$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 获取所有分类（用于筛选下拉框）
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>新闻管理 - 2308新闻后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-speedometer2"></i> 新闻后台</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">欢迎，<strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></span>
                <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-light me-2"><i class="bi bi-eye"></i> 查看网站</a>
                <a href="add_article.php" class="btn btn-sm btn-success me-2"><i class="bi bi-plus-circle"></i> 发布新闻</a>
                <a href="logout.php" class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right"></i> 退出</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- 操作反馈消息 -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-newspaper"></i> 新闻文章管理</h5>
                <span class="badge bg-primary">共 <?php echo $total_rows; ?> 篇文章</span>
            </div>
            <div class="card-body">
                <!-- 搜索和筛选栏 -->
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="搜索文章标题或内容..." name="search" value="<?php echo htmlspecialchars($search_keyword); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> 搜索</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="0">所有分类</option>
                            <?php while($cat = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="dashboard.php" class="btn btn-outline-secondary">重置筛选</a>
                    </div>
                </form>

                <!-- 文章列表表格 -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">ID</th>
                                <th>文章标题</th>
                                <th width="120">分类</th>
                                <th width="150">发布时间</th>
                                <th width="100">浏览量</th>
                                <th width="100">状态</th>
                                <th width="220" class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <a href="../article.php?id=<?php echo $row['id']; ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars(mb_substr($row['title'], 0, 50, 'utf-8')); ?>
                                            <?php if(mb_strlen($row['title'], 'utf-8') > 50): ?>...<?php endif; ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                    <td class="small"><?php echo date('Y-m-d H:i', strtotime($row['publish_date'])); ?></td>
                                    <td><span class="badge bg-secondary"><i class="bi bi-eye"></i> <?php echo $row['view_count']; ?></span></td>
                                    <td>
                                        <?php if($row['is_featured']): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-star"></i> 置顶</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">普通</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="../article.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-primary" title="预览"><i class="bi bi-eye"></i></a>
                                            <a href="edit_article.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-warning" title="编辑"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="btn btn-outline-danger" title="删除" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <!-- 删除确认模态框 (每个文章一个) -->
                                        <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> 确认删除</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>确定要删除文章 <strong>"<?php echo htmlspecialchars($row['title']); ?>"</strong> 吗？</p>
                                                        <p class="text-danger"><small>此操作不可恢复，且会删除文章对应的封面图片！</small></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                                        <a href="delete_article.php?id=<?php echo $row['id']; ?>" class="btn btn-danger">确认删除</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-newspaper display-6 text-muted d-block mb-3"></i>
                                        <h5 class="text-muted">暂无新闻文章</h5>
                                        <p class="text-muted">点击右上角 <a href="add_article.php" class="text-decoration-none">“发布新闻”</a> 按钮添加第一篇新闻。</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页导航 -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="文章列表分页" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page-1; ?><?php echo $search_keyword ? '&search='.urlencode($search_keyword) : ''; ?><?php echo $category_filter ? '&category='.$category_filter : ''; ?>">上一页</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_keyword ? '&search='.urlencode($search_keyword) : ''; ?><?php echo $category_filter ? '&category='.$category_filter : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page+1; ?><?php echo $search_keyword ? '&search='.urlencode($search_keyword) : ''; ?><?php echo $category_filter ? '&category='.$category_filter : ''; ?>">下一页</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- 后台快速统计 -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">文章总数</h6>
                                <h2 class="mb-0"><?php echo $total_rows; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-file-text display-6 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">今日发布</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $today_sql = "SELECT COUNT(*) as count FROM articles WHERE DATE(publish_date) = CURDATE()";
                                    $today_result = $conn->query($today_sql);
                                    echo $today_result ? $today_result->fetch_assoc()['count'] : 0;
                                    ?>
                                </h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-day display-6 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 可以添加更多统计卡片 -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $stmt->close(); ?>
