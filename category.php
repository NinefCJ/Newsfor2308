<?php
/**
 * category.php - 修复数据库连接和结果集错误
 * 按分类显示新闻文章
 */

// 确保错误报告开启
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 包含必要的文件
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 检查数据库连接是否成功
if (!isset($conn) || $conn === null) {
    die("<div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>数据库连接失败</h4>
                <p>无法连接到数据库，请检查 config.php 中的配置。</p>
                <p><a href='test_connection.php' class='btn btn-primary'>测试数据库连接</a></p>
            </div>
         </div>");
}

// 获取分类ID
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 初始化变量
$category = null;
$articles_result = null;
$stmt = null;
$total_pages = 0;
$total_rows = 0;

try {
    // 1. 查询分类信息
    if ($category_id > 0) {
        $cat_sql = "SELECT id, name FROM categories WHERE id = ?";
        
        // 检查 $conn 是否有效
        if (!$conn) {
            throw new Exception("数据库连接无效");
        }
        
        $cat_stmt = $conn->prepare($cat_sql);
        
        if (!$cat_stmt) {
            throw new Exception("准备分类查询失败: " . $conn->error);
        }
        
        $cat_stmt->bind_param("i", $category_id);
        
        if (!$cat_stmt->execute()) {
            throw new Exception("执行分类查询失败: " . $cat_stmt->error);
        }
        
        $cat_result = $cat_stmt->get_result();
        
        if (!$cat_result) {
            throw new Exception("获取分类结果集失败");
        }
        
        $category = $cat_result->fetch_assoc();
        
        // 释放结果集
        if ($cat_result) {
            $cat_result->free();
        }
        
        $cat_stmt->close();
        
        if (!$category) {
            // 分类不存在，重定向到首页
            header('Location: index.php');
            exit();
        }
        
        $page_title = $category['name'];
    } else {
        // 没有分类ID，重定向到首页
        header('Location: index.php');
        exit();
    }
    
    // 2. 分页设置
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $items_per_page = 12;
    $offset = ($current_page - 1) * $items_per_page;
    
    // 3. 查询该分类下的文章
    $sql = "SELECT SQL_CALC_FOUND_ROWS 
                   a.id, a.title, a.content, a.publish_date, 
                   a.view_count, a.cover_image, a.author
            FROM articles a
            WHERE a.category_id = ?
            ORDER BY a.publish_date DESC
            LIMIT ? OFFSET ?";
    
    // 准备查询语句
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("准备文章查询失败: " . $conn->error);
    }
    
    $stmt->bind_param("iii", $category_id, $items_per_page, $offset);
    
    if (!$stmt->execute()) {
        throw new Exception("执行文章查询失败: " . $stmt->error);
    }
    
    $articles_result = $stmt->get_result();
    
    if (!$articles_result) {
        throw new Exception("获取文章结果集失败");
    }
    
    // 4. 获取总行数
    $total_rows_result = $conn->query("SELECT FOUND_ROWS()");
    
    if (!$total_rows_result) {
        throw new Exception("获取总行数失败");
    }
    
    $total_rows_row = $total_rows_result->fetch_row();
    $total_rows = $total_rows_row ? $total_rows_row[0] : 0;
    $total_pages = ceil($total_rows / $items_per_page);
    
    // 释放总行数结果集
    if ($total_rows_result) {
        $total_rows_result->free();
    }
    
} catch (Exception $e) {
    // 错误处理
    $error_message = $e->getMessage();
    
    // 包含头部文件，即使出错也要显示基本结构
    $page_title = '错误 - ' . ($category ? $category['name'] : '分类页面');
    require_once 'includes/header.php';
    
    echo "<div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>系统错误 - 分类页面</h4>
                <p>" . htmlspecialchars($error_message) . "</p>
                <p>请检查数据库连接或联系管理员。</p>
                <a href='index.php' class='btn btn-primary'>返回首页</a>
                <a href='test_connection.php' class='btn btn-secondary'>测试数据库连接</a>
            </div>
         </div>";
    
    // 包含页脚文件
    require_once 'includes/footer.php';
    exit();
}

// 如果没有错误，正常包含头部文件
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- 分类标题和统计 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><?php echo safe_output($category['name']); ?></h1>
                <p class="text-muted mb-0">共 <?php echo $total_rows; ?> 篇文章</p>
            </div>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> 返回所有分类
            </a>
        </div>

        <?php if ($articles_result && $articles_result->num_rows > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php 
                // 确保结果集可用
                if ($articles_result) {
                    $displayed_articles = [];
                    
                    while($row = $articles_result->fetch_assoc()): 
                        if (in_array($row['id'], $displayed_articles)) {
                            continue;
                        }
                        $displayed_articles[] = $row['id'];
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm hover-shadow">
                        <?php if($row['cover_image']): ?>
                        <img src="<?php echo safe_output($row['cover_image']); ?>" 
                             class="card-img-top" 
                             alt="<?php echo safe_output($row['title']); ?>"
                             style="height: 180px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">
                                <a href="article.php?id=<?php echo $row['id']; ?>" 
                                   class="text-decoration-none text-dark stretched-link">
                                    <?php 
                                    $title = safe_output($row['title']);
                                    echo mb_strlen($title, 'utf-8') > 40 
                                        ? mb_substr($title, 0, 40, 'utf-8') . '...' 
                                        : $title;
                                    ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo get_excerpt($row['content'], 80); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?php echo safe_output($row['author']); ?>
                                </small>
                                <div>
                                    <small class="text-muted me-2">
                                        <i class="bi bi-calendar"></i> <?php echo date('m-d', strtotime($row['publish_date'])); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="bi bi-eye"></i> <?php echo $row['view_count']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    endwhile;
                } else {
                    echo '<div class="col-12"><p class="text-center text-muted">无法读取文章数据</p></div>';
                }
                ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="分页导航" class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="category.php?id=<?php echo $category_id; ?>&page=<?php echo $current_page - 1; ?>">
                            <i class="bi bi-chevron-left"></i> 上一页
                        </a>
                    </li>
                    
                    <?php
                    // 显示页码（最多显示7个页码）
                    $start_page = max(1, $current_page - 3);
                    $end_page = min($total_pages, $current_page + 3);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="category.php?id='.$category_id.'&page=1">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="category.php?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                        <li class="page-item"><a class="page-link" href="category.php?id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="category.php?id=<?php echo $category_id; ?>&page=<?php echo $current_page + 1; ?>">
                            下一页 <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5 my-5">
                <i class="bi bi-folder-x display-1 text-muted mb-3"></i>
                <h3 class="text-muted">该分类下暂无内容</h3>
                <p class="text-muted">新闻部正在积极准备相关文章...</p>
                <a href="index.php" class="btn btn-primary mt-3">浏览所有新闻</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<?php
// 清理数据库资源
if (isset($articles_result) && $articles_result) {
    $articles_result->free();
}

if (isset($stmt) && $stmt) {
    $stmt->close();
}

// 注意：不要在这里关闭数据库连接 $conn->close();
require_once 'includes/footer.php';
?>
