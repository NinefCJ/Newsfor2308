<?php
// fix_duplicates.php - 文章重复修复工具
require_once 'includes/config.php';

// 验证访问权限（简单验证）
session_start();
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['public_fix'])) {
    // 如果不是管理员，显示简化版本
    $show_public_version = true;
} else {
    $show_public_version = false;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>文章重复修复工具</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .card { margin-bottom: 20px; }
        .duplicate-item { border-left: 4px solid #dc3545; }
        .keep-item { border-left: 4px solid #198754; }
    </style>
</head>
<body class='container mt-4'>
<h2 class='mb-4'>🔧 文章重复修复工具</h2>";

// 检查数据库连接
if (!$conn || $conn->connect_error) {
    die("<div class='alert alert-danger'>数据库连接失败: " . ($conn ? $conn->connect_error : '连接对象为空') . "</div>");
}

// 处理快速修复请求
if (isset($_GET['action']) && $_GET['action'] == 'quick_fix') {
    echo "<div class='card'>
            <div class='card-header'>快速修复执行结果</div>
            <div class='card-body'>";
    
    // 修复逻辑：删除完全重复的文章，保留最早的一篇
    $sql = "DELETE a1 FROM articles a1
            INNER JOIN articles a2 
            WHERE a1.id > a2.id 
            AND a1.title = a2.title 
            AND a1.content = a2.content";
    
    if ($conn->query($sql)) {
        $affected_rows = $conn->affected_rows;
        echo "<div class='alert alert-success'>
                <h5>✓ 快速修复完成</h5>
                <p>已删除 {$affected_rows} 篇完全重复的文章（保留最早的一篇）</p>
                <p><a href='diagnose_articles.php'>返回诊断页面查看结果</a></p>
              </div>";
    } else {
        echo "<div class='alert alert-danger'>
                <h5>✗ 修复失败</h5>
                <p>错误信息: " . $conn->error . "</p>
              </div>";
    }
    
    echo "</div></div>";
    $conn->close();
    exit();
}

// 处理预防措施请求
if (isset($_POST['prevent_duplicates'])) {
    echo "<div class='card'>
            <div class='card-header'>预防措施执行结果</div>
            <div class='card-body'>";
    
    $actions_taken = [];
    
    // 1. 添加唯一索引（如果用户选择）
    if (isset($_POST['add_unique_index'])) {
        // 尝试添加唯一索引
        $sql = "ALTER TABLE articles ADD UNIQUE INDEX idx_unique_title (title(200))";
        if ($conn->query($sql)) {
            $actions_taken[] = "✓ 已添加唯一索引 (idx_unique_title)";
        } else {
            $actions_taken[] = "⚠ 添加唯一索引失败: " . $conn->error;
        }
    }
    
    // 2. 检查并修复数据
    if (empty($actions_taken)) {
        $actions_taken[] = "未选择任何预防措施";
    }
    
    echo "<h5>执行结果:</h5>";
    echo "<ul>";
    foreach ($actions_taken as $action) {
        echo "<li>{$action}</li>";
    }
    echo "</ul>";
    
    echo "</div></div>";
}

// 显示重复文章列表供手动修复
echo "<div class='card'>
        <div class='card-header'>手动修复 - 选择要保留的文章</div>
        <div class='card-body'>";

// 查找重复文章
$sql = "SELECT title, GROUP_CONCAT(id ORDER BY publish_date) as ids, 
               COUNT(*) as count, MIN(publish_date) as earliest_date
        FROM articles 
        GROUP BY title 
        HAVING count > 1
        ORDER BY count DESC, earliest_date ASC
        LIMIT 20";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<form method='POST' action='process_duplicates.php'>";
    echo "<p class='text-muted mb-3'>找到 {$result->num_rows} 组重复文章</p>";
    
    $group_num = 1;
    while($row = $result->fetch_assoc()) {
        $ids = explode(',', $row['ids']);
        $article_count = count($ids);
        
        echo "<div class='card duplicate-item mb-3'>
                <div class='card-header'>
                    <strong>第 {$group_num} 组：</strong> {$row['title']}
                    <span class='badge bg-danger float-end'>{$row['count']} 篇重复</span>
                </div>
                <div class='card-body'>";
        
        // 查询每篇重复文章的详细信息
        foreach ($ids as $index => $id) {
            $detail_sql = "SELECT id, title, publish_date, view_count, author 
                          FROM articles 
                          WHERE id = ?";
            $stmt = $conn->prepare($detail_sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $detail_result = $stmt->get_result();
            $article = $detail_result->fetch_assoc();
            $stmt->close();
            
            $is_first = ($index == 0);
            $is_last = ($index == count($ids) - 1);
            
            echo "<div class='form-check mb-2 " . ($is_first ? 'keep-item p-2' : '') . "'>
                    <input class='form-check-input' type='radio' 
                           name='keep_article[{$row['title']}]' 
                           value='{$article['id']}' 
                           id='article_{$article['id']}' 
                           " . ($is_first ? 'checked' : '') . ">
                    <label class='form-check-label' for='article_{$article['id']}'>
                        <strong>ID: {$article['id']}</strong> - 
                        发布于: {$article['publish_date']} - 
                        作者: {$article['author']} - 
                        浏览量: {$article['view_count']}
                        " . ($is_first ? ' <span class="badge bg-success">最早的一篇（建议保留）</span>' : '') . "
                    </label>
                  </div>";
        }
        
        echo "<div class='mt-2'>
                <div class='form-check form-check-inline'>
                    <input class='form-check-input' type='checkbox' 
                           id='delete_all_{$group_num}' name='delete_all[]' value='{$row['title']}'>
                    <label class='form-check-label text-danger' for='delete_all_{$group_num}'>
                        删除这一组的全部文章
                    </label>
                </div>
              </div>";
        
        echo "</div></div>";
        $group_num++;
    }
    
    echo "<div class='mt-4'>
            <button type='submit' class='btn btn-danger btn-lg' name='process_duplicates'>
                🗑️ 执行删除操作
            </button>
            <a href='diagnose_articles.php' class='btn btn-secondary'>返回诊断</a>
            <p class='text-muted mt-2 small'>
                <strong>注意：</strong>此操作不可逆！将删除所有未选中的重复文章。
            </p>
          </div>";
    
    echo "</form>";
} else {
    echo "<div class='alert alert-success'>
            <h5>✓ 恭喜！</h5>
            <p>未发现重复文章，您的数据库是干净的。</p>
            <p><a href='diagnose_articles.php'>返回诊断页面</a></p>
          </div>";
}

echo "</div></div>";

// 显示统计信息
echo "<div class='card'>
        <div class='card-header'>数据库统计</div>
        <div class='card-body'>";

$sql = "SELECT 
        (SELECT COUNT(*) FROM articles) as total_articles,
        (SELECT COUNT(DISTINCT title) FROM articles) as unique_titles,
        (SELECT COUNT(*) - COUNT(DISTINCT title) FROM articles) as duplicate_count";
$result = $conn->query($sql);
$stats = $result->fetch_assoc();

echo "<div class='row text-center'>
        <div class='col-md-4'>
            <div class='card bg-light'>
                <div class='card-body'>
                    <h3>{$stats['total_articles']}</h3>
                    <p class='text-muted'>文章总数</p>
                </div>
            </div>
        </div>
        <div class='col-md-4'>
            <div class='card bg-light'>
                <div class='card-body'>
                    <h3>{$stats['unique_titles']}</h3>
                    <p class='text-muted'>唯一标题数</p>
                </div>
            </div>
        </div>
        <div class='col-md-4'>
            <div class='card " . ($stats['duplicate_count'] > 0 ? 'bg-warning' : 'bg-light') . "'>
                <div class='card-body'>
                    <h3>{$stats['duplicate_count']}</h3>
                    <p class='text-muted'>重复文章数</p>
                </div>
            </div>
        </div>
      </div>";

echo "</div></div>";

$conn->close();
?>

<div class='alert alert-info mt-4'>
    <h5>使用说明：</h5>
    <ol>
        <li>选择每组重复文章中要保留的一篇（默认选择最早的一篇）</li>
        <li>点击"执行删除操作"按钮，删除其他重复文章</li>
        <li>操作完成后，返回诊断页面验证修复结果</li>
        <li>建议添加唯一索引以防止未来再次出现重复</li>
    </ol>
</div>

<p><a href='index.php' class='btn btn-outline-secondary'>返回首页</a></p>
</body>
</html>
