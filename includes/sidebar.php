<?php
// 侧边栏模块
// 注意：此文件应在数据库连接已建立的情况下被包含

// 查询热门文章（按浏览量，最近30天内）
$popular_sql = "SELECT id, title, view_count 
                FROM articles 
                WHERE publish_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY view_count DESC 
                LIMIT 5";
$popular_result = $conn->query($popular_sql);

// 查询最新文章
$latest_sql = "SELECT id, title, publish_date 
               FROM articles 
               ORDER BY publish_date DESC 
               LIMIT 5";
$latest_result = $conn->query($latest_sql);

// 查询所有分类及文章计数
$categories_sql = "SELECT c.id, c.name, COUNT(a.id) as article_count 
                   FROM categories c 
                   LEFT JOIN articles a ON c.id = a.category_id 
                   GROUP BY c.id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_sql);
?>

<!-- 搜索框（功能扩展预留） -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-search me-2"></i>搜索新闻</h5>
        <form action="search.php" method="get" class="d-flex">
            <input type="text" class="form-control me-2" placeholder="输入关键词..." name="q" disabled>
            <button type="submit" class="btn btn-primary" disabled><i class="bi bi-search"></i></button>
        </form>
        <p class="small text-muted mt-2 mb-0">搜索功能开发中...</p>
    </div>
</div>

<!-- 分类云 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-folder me-2"></i>新闻分类</h5>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                <?php while($cat = $categories_result->fetch_assoc()): ?>
                    <a href="category.php?id=<?php echo $cat['id']; ?>" 
                       class="btn btn-sm btn-outline-secondary position-relative">
                        <?php echo safe_output($cat['name']); ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                            <?php echo $cat['article_count']; ?>
                        </span>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">暂无分类</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 热门文章 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-fire me-2"></i>热门文章</h5>
        <div class="list-group list-group-flush">
            <?php if ($popular_result && $popular_result->num_rows > 0): ?>
                <?php while($popular = $popular_result->fetch_assoc()): ?>
                <a href="article.php?id=<?php echo $popular['id']; ?>" 
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                    <span class="text-truncate" style="max-width: 70%;">
                        <?php echo safe_output($popular['title']); ?>
                    </span>
                    <span class="badge bg-primary rounded-pill"><?php echo $popular['view_count']; ?></span>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">暂无热门文章</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 最新文章 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-clock me-2"></i>最新发布</h5>
        <div class="list-group list-group-flush">
            <?php if ($latest_result && $latest_result->num_rows > 0): ?>
                <?php while($latest = $latest_result->fetch_assoc()): ?>
                <a href="article.php?id=<?php echo $latest['id']; ?>" 
                   class="list-group-item list-group-item-action py-2">
                    <div class="fw-semibold small text-truncate"><?php echo safe_output($latest['title']); ?></div>
                    <div class="text-muted xsmall">
                        <?php echo date('m月d日', strtotime($latest['publish_date'])); ?>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">暂无最新文章</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 网站信息 -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>网站信息</h5>
        <ul class="list-unstyled small">
            <?php
            // 统计文章总数
            $total_articles_sql = "SELECT COUNT(*) as total FROM articles";
            $total_articles_result = $conn->query($total_articles_sql);
            $total_articles = $total_articles_result ? $total_articles_result->fetch_assoc()['total'] : 0;
            
            // 统计总浏览量
            $total_views_sql = "SELECT SUM(view_count) as total_views FROM articles";
            $total_views_result = $conn->query($total_views_sql);
            $total_views = $total_views_result ? $total_views_result->fetch_assoc()['total_views'] : 0;
            ?>
            <li class="mb-1"><i class="bi bi-file-text me-2"></i> 文章总数：<?php echo $total_articles; ?> 篇</li>
            <li class="mb-1"><i class="bi bi-eye me-2"></i> 总浏览量：<?php echo number_format($total_views); ?> 次</li>
            <li class="mb-1"><i class="bi bi-calendar me-2"></i> 运行时间：2026年2月至今</li>
            <li class="mb-1"><i class="bi bi-code-slash me-2"></i> 技术支持：PHP + MySQL</li>
        </ul>
        <div class="text-center mt-3">
            <a href="admin/login.php" class="btn btn-sm btn-outline-primary">管理后台</a>
        </div>
    </div>
</div>
