<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 检查文章ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 404 Not Found'<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 新增：更强大的内容格式化函数
function format_article_content($content) {
    if (empty($content)) {
        return '';
    }
    
    // 调试：记录原始内容格式
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("原始内容前100字符: " . substr($content, 0, 100));
        error_log("原始内容包含反斜杠: " . (strpos($content, '\\') !== false ? '是' : '否'));
    }
    
    // 1. 去除多余的转义反斜杠
    // 处理双重转义：将 \\r\\n 转换为 \r\n
    $content = stripslashes($content);
    
    // 2. 处理可能被双重转义的内容
    // 如果内容中包含字面反斜杠+字母的组合，转换为实际字符
    $content = str_replace(['\r\n', '\r', '\n', '\t'], ["\r\n", "\r", "\n", "\t"], $content);
    
    // 3. 解码HTML实体（如果内容被编码了）
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // 4. 将各种换行符统一为HTML换行标签
    $content = nl2br($content, false); // false表示不使用XHTML格式（<br />）
    
    // 5. 清理多余的空白字符
    $content = preg_replace('/\s+/', ' ', $content);
    $content = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $content);
    
    // 6. 处理特殊字符（确保HTML安全）
    // 注意：这里我们不使用htmlspecialchars，因为内容可能包含合法的HTML标签
    
    return $content;
}

// 新增：更强大的视频路径修复函数
function fix_video_path($video_path) {
    if (empty($video_path)) {
        return '';
    }
    
    // 调试信息
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("原始视频路径: " . $video_path);
    }
    
    // 去除首尾空格和可能的引号
    $video_path = trim($video_path, " \t\n\r\0\x0B\"'");
    
    // 如果已经是完整的URL，直接返回
    if (strpos($video_path, 'http://') === 0 || strpos($video_path, 'https://') === 0) {
        return $video_path;
    }
    
    // 检查是否是绝对路径（以/开头）
    if (strpos($video_path, '/') === 0) {
        // 已经是绝对路径
        $fixed_path = $video_path;
    } else {
        // 相对路径，添加uploads前缀（假设视频存储在uploads目录）
        // 根据您的实际情况调整这个路径
        $fixed_path = '/uploads/' . ltrim($video_path, '/');
    }
    
    // 调试信息
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("修复后视频路径: " . $fixed_path);
        
        // 检查文件是否存在（仅用于本地文件）
        $local_path = $_SERVER['DOCUMENT_ROOT'] . $fixed_path;
        if (file_exists($local_path)) {
            error_log("文件存在: " . $local_path);
            error_log("文件大小: " . filesize($local_path) . " 字节");
            error_log("文件类型: " . mime_content_type($local_path));
        } else {
            error_log("文件不存在: " . $local_path);
        }
    }
    
    return $fixed_path;
}

// 新增：检查视频文件是否存在并返回适当的信息
function get_video_info($video_path) {
    if (empty($video_path)) {
        return ['exists' => false, 'error' => '视频路径为空'];
    }
    
    // 如果是远程URL，直接返回
    if (strpos($video_path, 'http://') === 0 || strpos($video_path, 'https://') === 0) {
        return ['exists' => true, 'type' => 'remote', 'path' => $video_path];
    }
    
    // 本地文件检查
    $local_path = $_SERVER['DOCUMENT_ROOT'] . $video_path;
    
    if (!file_exists($local_path)) {
        return ['exists' => false, 'error' => '文件不存在: ' . $local_path, 'path' => $video_path];
    }
    
    // 获取文件信息
    $file_info = [
        'exists' => true,
        'type' => 'local',
        'path' => $video_path,
        'size' => filesize($local_path),
        'mime_type' => mime_content_type($local_path),
        'last_modified' => date('Y-m-d H:i:s', filemtime($local_path))
    ];
    
    return $file_info;
}

// 检查文章ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 404 Not Found');
    include '404.php';
    exit();
}

$article_id = intval($_GET['id']);

// 首先增加浏览量
increase_view_count($conn, $article_id);

// 查询文章详情
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

// 处理文章内容中的换行符
if (!empty($article['content'])) {
    $original_content = $article['content'];
    $article['content'] = format_article_content($article['content']);
    
    // 调试：比较处理前后的差异
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("处理前内容示例: " . substr($original_content, 0, 200));
        error_log("处理后内容示例: " . substr($article['content'], 0, 200));
    }
}

// 处理视频路径
$video_info = null;
if (!empty($article['video_url'])) {
    $article['video_url'] = fix_video_path($article['video_url']);
    $video_info = get_video_info($article['video_url']);
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
        
        <!-- 视频播放器 -->
        <?php if(!empty($article['video_url'])): ?>
        <div class="text-center mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-camera-video"></i> 视频内容</h5>
                    
                    <?php if($video_info && $video_info['exists']): ?>
                        <div class="ratio ratio-16x9">
                            <video controls class="rounded" style="max-width: 100%;">
                                <source src="<?php echo safe_output($article['video_url']); ?>" type="video/mp4">
                                您的浏览器不支持视频播放，请尝试使用Chrome、Firefox等现代浏览器。
                            </video>
                        </div>
                        <div class="mt-2">
                            <p class="text-muted small mb-1">
                                <strong>视频信息：</strong>
                                <?php if($video_info['type'] === 'local'): ?>
                                    本地文件 (<?php echo round($video_info['size'] / 1024 / 1024, 2); ?> MB)
                                <?php else: ?>
                                    远程视频
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            视频文件无法访问
                            <?php if($video_info && isset($video_info['error'])): ?>
                                <div class="small mt-1">错误：<?php echo safe_output($video_info['error']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-start small text-muted">
                            <p><strong>视频路径：</strong> <?php echo safe_output($article['video_url']); ?></p>
                            <p><strong>修复建议：</strong></p>
                            <ol class="mb-0">
                                <li>检查文件是否存在于服务器上</li>
                                <li>确认文件权限是否正确（通常应为644）</li>
                                <li>检查Nginx/Apache配置是否正确</li>
                                <li>确保视频格式为MP4(H.264编码)</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <!-- 文章正文 -->
    <div class="news-content fs-5 lh-base">
        <?php 
        // 直接输出处理后的内容
        // 注意：确保内容已经过安全处理
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

// 调试信息（仅限开发环境使用）
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    echo '<div class="mt-4 p-3 bg-light border rounded small">';
    echo '<h6>调试信息</h6>';
    echo '<pre>';
    echo '文章ID: ' . $article_id . "\n";
    echo '视频URL: ' . htmlspecialchars($article['video_url']) . "\n";
    
    if ($video_info) {
        echo "\n视频检查结果:\n";
        foreach ($video_info as $key => $value) {
            echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
    
    echo "\n内容处理示例:\n";
    echo "原始前50字符: " . htmlspecialchars(substr($original_content ?? '', 0, 50)) . "\n";
    echo "处理后前50字符: " . htmlspecialchars(substr($article['content'], 0, 50)) . "\n";
    echo '</pre>';
    echo '</div>';
}

require_once 'includes/footer.php';
?>
);
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
