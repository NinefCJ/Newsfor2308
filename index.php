<?php
// index.php - 修复重复文章显示问题
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 页面标题
$page_title = '最新动态';

// 分页设置
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// 初始化变量
$featured_result = null;
$articles_result = null;
$stmt = null;

try {
    // 1. 获取置顶文章 - 使用 DISTINCT 避免重复
    $featured_sql = "SELECT DISTINCT id, title 
                     FROM articles 
                     WHERE is_featured = 1 
                     ORDER BY publish_date DESC 
                     LIMIT 3";
    $featured_result = $conn->query($featured_sql);
    
    // 2. 获取普通文章 - 按标题分组，只取最新的
    $sql = "SELECT a.*, c.name as category_name
            FROM (
                SELECT MAX(id) as latest_id, title
                FROM articles 
                WHERE is_featured = 0
                GROUP BY title  -- 关键：按标题分组，避免重复
            ) as latest_articles
            INNER JOIN articles a ON latest_articles.latest_id = a.id
            LEFT JOIN categories c ON a.category_id = c.id
            ORDER BY a.publish_date DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $items_per_page, $offset);
    $stmt->execute();
    $articles_result = $stmt->get_result();
    
    // 3. 获取总行数（需要单独查询）
    $count_sql = "SELECT COUNT(DISTINCT title) as total 
                  FROM articles 
                  WHERE is_featured = 0";
    $count_result = $conn->query($count_sql);
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $items_per_page);
    
    // 4. 包含头部文件
    require_once 'includes/header.php';
    
} catch (Exception $e) {
    die("<div class='container mt-5'>
            <div class='alert alert-danger'>错误: " . htmlspecialchars($e->getMessage()) . "</div>
         </div>");
}
?>

<div class="row">
    <!-- 主内容区 -->
    <div class="col-lg-8">
        <!-- 置顶公告 -->
        <?php if ($featured_result && $featured_result->num_rows > 0): ?>
        <div class="alert alert-info">
            <h5><i class="bi bi-pin-angle"></i> 置顶公告</h5>
            <ul class="mb-0">
                <?php 
                // 防止重复显示
                $displayed_featured = [];
                while($featured = $featured_result->fetch_assoc()): 
                    if (in_array($featured['id'], $displayed_featured)) {
                        continue;
                    }
                    $displayed_featured[] = $featured['id'];
                ?>
                <li>
                    <a href="article.php?id=<?php echo $featured['id']; ?>" class="alert-link">
                        <?php echo safe_output($featured['title']); ?>
                    </a>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>

        <h1 class="mb-4 border-bottom pb-2">最新动态</h1>

        <?php if ($articles_result && $articles_result->num_rows > 0): ?>
            <?php 
            // 使用数组记录已显示的文章ID，防止重复
            $displayed_articles = [];
            while($row = $articles_result->fetch_assoc()): 
                if (in_array($row['id'], $displayed_articles)) {
                    continue;
                }
                $displayed_articles[] = $row['id'];
            ?>
            <article class="card mb-4 shadow-sm hover-shadow">
                <!-- 文章内容保持不变 -->
                <div class="row g-0">
                    <?php if($row['cover_image']): ?>
                    <div class="col-md-4">
                        <img src="<?php echo safe_output($row['cover_image']); ?>" 
                             class="img-fluid rounded-start" 
                             alt="封面" style="height: 200px; object-fit: cover; width: 100%;">
                    </div>
                    <div class="col-md-8">
                    <?php else: ?>
                    <div class="col-md-12">
                    <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="badge bg-primary"><?php echo safe_output($row['category_name']); ?></span>
                                <small class="text-muted"><?php echo date('m月d日', strtotime($row['publish_date'])); ?></small>
                            </div>
                            <h5 class="card-title mt-2">
                                <a href="article.php?id=<?php echo $row['id']; ?>" class="text-decoration-none text-dark stretched-link">
                                    <?php echo safe_output($row['title']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted">
                                <?php echo get_excerpt($row['content']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <small class="text-muted"><i class="bi bi-person"></i> <?php echo safe_output($row['author']); ?></small>
                                <small class="text-muted"><i class="bi bi-eye"></i> <?php echo $row['view_count']; ?> 浏览</small>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
            <?php endwhile; ?>
            
            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="新闻列表分页">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">上一页</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">下一页</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-newspaper display-1 text-muted"></i>
                <h3 class="mt-3">暂无新闻</h3>
                <p class="text-muted">新闻部正在紧张筹备中...</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 侧边栏 -->
    <div class="col-lg-4">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<?php
// 清理资源
if ($featured_result) $featured_result->free();
if ($articles_result) $articles_result->free();
if ($stmt) $stmt->close();

require_once 'includes/footer.php';
?>
