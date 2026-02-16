<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 检查文章ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 404 Not Found');
    include '404.php'; // 可创建自定义404页面
    exit();
}

$article_id = intval($_GET['id']);

// 首先增加浏览量（确保即使查询失败也记录访问尝试）
increase_view_count($conn, $article_id);

// 查询文章详情（使用预处理语句防止SQL注入）
$sql = "SELECT a.*, c.name as category_name 
        FROM articles a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();
$stmt->close();

// 如果文章不存在，返回404
if (!$article) {
    header('HTTP/1.1 404 Not Found');
    include '404.php';
    exit();
}

// 设置页面标题
$page_title = $article['title'];
require_once 'includes/header.php';

// 查询上一篇和下一篇
$prev_next_sql = "(
    SELECT id, title, 'prev' as type 
    FROM articles 
    WHERE publish_date < ? AND id != ? 
    ORDER BY publish_date DESC 
    LIMIT 1
) UNION ALL (
    SELECT id, title, 'next' as type 
    FROM articles 
    WHERE publish_date > ? AND id != ? 
    ORDER BY publish_date ASC 
    LIMIT 1
)";
$stmt = $conn->prepare($prev_next_sql);
$publish_date = $article['publish_date'];
$stmt->bind_param("sisi", $publish_date, $article_id, $publish_date, $article_id);
$stmt->execute();
$prev_next_result = $stmt->get_result();
$prev_next = ['prev' => null, 'next' => null];
while ($row = $prev_next_result->fetch_assoc()) {
    $prev_next[$row['type']] = $row;
}
$stmt->close();
?>

<article class="news-detail">
    <!-- 面包屑导航 -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">首页</a></li>
            <li class="breadcrumb-item"><a href="category.php?id=<?php echo $article['category_id']; ?>"><?php echo safe_output($article['category_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">正文</li>
        </ol>
    </nav>

    <!-- 文章头部 -->
    <header class="mb-4">
        <h1 class="display-5 fw-bold"><?php echo safe_output($article['title']); ?></h1>
        <div class="d-flex flex-wrap gap-3 text-muted mb-3">
            <span><i class="bi bi-folder"></i> <?php echo safe_output($article['category_name']); ?></span>
            <span><i class="bi bi-person"></i> <?php echo safe_output($article['author']); ?></span>
            <span><i class="bi bi-clock"></i> <?php echo date('Y年m月d日 H:i', strtotime($article['publish_date'])); ?></span>
            <span><i class="bi bi-eye"></i> <?php echo number_format($article['view_count']); ?> 次浏览</span>
        </div>
        
        <!-- 封面图 -->
        <?php if($article['cover_image']): ?>
        <div class="text-center mb-4">
            <img src="<?php echo safe_output($article['cover_image']); ?>" 
                 class="img-fluid rounded shadow" 
                 alt="<?php echo safe_output($article['title']); ?>"
                 style="max-height: 400px; width: auto; object-fit: contain;">
            <?php if(strpos($article['cover_image'], 'http') !== 0): ?>
            <p class="text-muted small mt-2">封面图片</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <!-- 文章正文 -->
    <div class="news-content fs-5 lh-base">
        <?php 
        // 直接输出HTML内容（因为内容来自富文本编辑器，已包含HTML标签）
        // 注意：确保内容在入库时已经过安全过滤
        echo $article['content']; 
        ?>
    </div>

    <!-- 文章标签/分类 -->
    <div class="mt-5 pt-4 border-top">
        <div class="d-flex align-items-center">
            <span class="me-2"><i class="bi bi-tags"></i> 标签：</span>
            <a href="category.php?id=<?php echo $article['category_id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                <?php echo safe_output($article['category_name']); ?>
            </a>
            <?php if($article['is_featured']): ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-star"></i> 置顶</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 上一篇/下一篇导航 -->
    <nav class="mt-5" aria-label="文章导航">
        <div class="row">
            <div class="col-md-6 mb-3">
                <?php if($prev_next['prev']): ?>
                <a href="article.php?id=<?php echo $prev_next['prev']['id']; ?>" class="card border h-100 text-decoration-none hover-shadow">
                    <div class="card-body">
                        <div class="text-muted small mb-2"><i class="bi bi-chevron-left"></i> 上一篇</div>
                        <div class="fw-semibold text-truncate"><?php echo safe_output($prev_next['prev']['title']); ?></div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <?php if($prev_next['next']): ?>
                <a href="article.php?id=<?php echo $prev_next['next']['id']; ?>" class="card border h-100 text-decoration-none hover-shadow text-end">
                    <div class="card-body">
                        <div class="text-muted small mb-2">下一篇 <i class="bi bi-chevron-right"></i></div>
                        <div class="fw-semibold text-truncate"><?php echo safe_output($prev_next['next']['title']); ?></div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 返回列表按钮 -->
    <div class="mt-4 text-center">
        <a href="javascript:history.back()" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> 返回</a>
        <a href="index.php" class="btn btn-primary"><i class="bi bi-house"></i> 返回首页</a>
    </div>
</article>

<?php
// 查询相关文章（同分类的其他文章）
$related_sql = "SELECT id, title, publish_date, view_count 
                FROM articles 
                WHERE category_id = ? AND id != ? 
                ORDER BY publish_date DESC 
                LIMIT 5";
$stmt = $conn->prepare($related_sql);
$stmt->bind_param("ii", $article['category_id'], $article_id);
$stmt->execute();
$related_result = $stmt->get_result();

if ($related_result && $related_result->num_rows > 0):
?>
<div class="mt-5 pt-4 border-top">
    <h4 class="mb-3"><i class="bi bi-link-45deg"></i> 相关新闻</h4>
    <div class="list-group">
        <?php while($related = $related_result->fetch_assoc()): ?>
        <a href="article.php?id=<?php echo $related['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><?php echo safe_output($related['title']); ?></span>
            <div>
                <small class="text-muted me-3"><?php echo date('m-d', strtotime($related['publish_date'])); ?></small>
                <span class="badge bg-light text-dark"><i class="bi bi-eye"></i> <?php echo $related['view_count']; ?></span>
            </div>
        </a>
        <?php endwhile; ?>
    </div>
</div>
<?php
$stmt->close();
endif;

require_once 'includes/footer.php';
?>
