<?php
/**
 * 404错误页面
 * 当访问不存在的页面时显示
 */

// 设置404 HTTP状态码
http_response_code(404);

// 页面标题
$page_title = '页面未找到 - 404错误';

// 包含配置文件
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 包含头部模板
require_once 'includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center align-items-center">
        <!-- 错误代码和图标区域 -->
        <div class="col-md-6 text-center text-md-end mb-5 mb-md-0">
            <div class="error-code-display">
                <h1 class="display-1 fw-bold text-primary" style="font-size: 10rem;">404</h1>
                <div class="mt-4">
                    <i class="bi bi-emoji-dizzy display-1 text-muted"></i>
                </div>
            </div>
        </div>
        
        <!-- 错误信息和操作区域 -->
        <div class="col-md-6 text-center text-md-start">
            <div class="error-content">
                <h2 class="h1 mb-4">页面不见啦！</h2>
                <p class="lead text-muted mb-4">
                    很抱歉，您正在寻找的页面可能已被移除、重命名或暂时不可用。
                </p>
                
                <!-- 可能的错误原因 -->
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-question-circle text-primary me-2"></i>可能的原因：</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-dot text-primary"></i> 网页地址拼写错误</li>
                            <li class="mb-2"><i class="bi bi-dot text-primary"></i> 文章已被删除或移动到新位置</li>
                            <li class="mb-2"><i class="bi bi-dot text-primary"></i> 链接已过期或失效</li>
                            <li class="mb-2"><i class="bi bi-dot text-primary"></i> 临时性的服务器问题</li>
                        </ul>
                    </div>
                </div>
                
                <!-- 操作按钮 -->
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 mb-5">
                    <a href="javascript:history.back()" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-arrow-left me-2"></i>返回上一页
                    </a>
                    <a href="index.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-house me-2"></i>返回首页
                    </a>
                </div>
                
                <!-- 搜索建议 -->
                <div class="search-suggestion">
                    <p class="text-muted mb-3">或者，尝试搜索您需要的内容：</p>
                    <form action="search.php" method="get" class="d-flex" style="max-width: 400px;">
                        <input type="text" class="form-control me-2" placeholder="输入关键词搜索..." name="q" disabled>
                        <button type="submit" class="btn btn-secondary" disabled><i class="bi bi-search"></i></button>
                    </form>
                    <p class="small text-muted mt-2">搜索功能开发中，敬请期待...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 热门文章推荐 -->
    <div class="row mt-5 pt-5 border-top">
        <div class="col-12">
            <h4 class="text-center mb-4"><i class="bi bi-fire text-primary me-2"></i>热门文章推荐</h4>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php
                // 获取热门文章（最近30天浏览量最高的3篇文章）
                $popular_sql = "SELECT id, title, view_count, publish_date 
                               FROM articles 
                               WHERE publish_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               ORDER BY view_count DESC 
                               LIMIT 3";
                $popular_result = $conn->query($popular_sql);
                
                if ($popular_result && $popular_result->num_rows > 0):
                    while($popular = $popular_result->fetch_assoc()):
                ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="article.php?id=<?php echo $popular['id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo safe_output(mb_substr($popular['title'], 0, 35, 'utf-8')); ?>
                                    <?php if(mb_strlen($popular['title'], 'utf-8') > 35): ?>...<?php endif; ?>
                                </a>
                            </h5>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-calendar3"></i> <?php echo date('m月d日', strtotime($popular['publish_date'])); ?>
                                </small>
                                <span class="badge bg-primary">
                                    <i class="bi bi-eye"></i> <?php echo $popular['view_count']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 pt-0">
                            <a href="article.php?id=<?php echo $popular['id']; ?>" class="btn btn-sm btn-outline-primary w-100">阅读全文</a>
                        </div>
                    </div>
                </div>
                <?php 
                    endwhile;
                else:
                    // 如果没有热门文章，显示备选内容
                ?>
                <div class="col">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-newspaper display-6 text-muted mb-3"></i>
                            <p class="text-muted">暂无热门文章</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-clock-history display-6 text-muted mb-3"></i>
                            <p class="text-muted">看看最新发布</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-folder display-6 text-muted mb-3"></i>
                            <a href="index.php" class="text-decoration-none">浏览所有分类</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 联系支持 -->
    <div class="row mt-5 pt-4">
        <div class="col-12">
            <div class="alert alert-info border-0">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="bi bi-info-circle display-6"></i>
                    </div>
                    <div>
                        <h5 class="alert-heading">需要帮助？</h5>
                        <p class="mb-0">
                            如果您确信这个页面应该存在，或者遇到了其他问题，请联系2308班新闻部管理员。
                            <br>
                            <a href="about.php" class="alert-link">查看联系我们页面</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 包含页脚模板
require_once 'includes/footer.php';
?>
