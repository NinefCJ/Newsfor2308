<?php
// process_duplicates.php - 处理重复文章删除
require_once 'includes/config.php';

// 简单验证（实际应该更严格）
session_start();
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['public'])) {
    die("<div class='alert alert-danger'>需要管理员权限</div>");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>处理重复文章</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='container mt-4'>
<h2 class='mb-4'>🔄 处理重复文章</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_duplicates'])) {
    $keep_articles = $_POST['keep_article'] ?? [];
    $delete_all = $_POST['delete_all'] ?? [];
    
    $deleted_count = 0;
    $errors = [];
    
    echo "<div class='card'>
            <div class='card-header'>删除操作结果</div>
            <div class='card-body'>";
    
    // 处理每组重复文章
    foreach ($keep_articles as $title => $keep_id) {
        $title_safe = $conn->real_escape_string($title);
        
        // 如果选择了"删除全部"，则删除这组所有文章
        if (in_array($title, $delete_all)) {
            $sql = "DELETE FROM articles WHERE title = '{$title_safe}'";
            if ($conn->query($sql)) {
                $deleted_count += $conn->affected_rows;
                echo "<div class='alert alert-danger'>
                        <strong>删除全部：</strong> \"{$title}\" - 删除了 {$conn->affected_rows} 篇文章
                      </div>";
            } else {
                $errors[] = "删除 \"{$title}\" 失败: " . $conn->error;
            }
        } else {
            // 只删除未选中的文章
            $sql = "DELETE FROM articles WHERE title = '{$title_safe}' AND id != {$keep_id}";
            if ($conn->query($sql)) {
                $deleted_count += $conn->affected_rows;
                echo "<div class='alert alert-warning'>
                        <strong>保留 ID {$keep_id}：</strong> \"{$title}\" - 删除了 {$conn->affected_rows} 篇重复文章
                      </div>";
            } else {
                $errors[] = "删除 \"{$title}\" 的重复文章失败: " . $conn->error;
            }
        }
    }
    
    // 显示总结
    echo "<hr><h5>操作总结</h5>";
    echo "<p>总共删除了 <strong>{$deleted_count}</strong> 篇重复文章</p>";
    
    if (!empty($errors)) {
        echo "<div class='alert alert-danger mt-3'>
                <h6>遇到的错误：</h6>
                <ul>";
        foreach ($errors as $error) {
            echo "<li>{$error}</li>";
        }
        echo "</ul></div>";
    }
    
    echo "<div class='mt-4'>
            <a href='diagnose_articles.php' class='btn btn-primary'>查看修复结果</a>
            <a href='fix_duplicates.php' class='btn btn-secondary'>返回修复工具</a>
            <a href='index.php' class='btn btn-outline-secondary'>返回首页</a>
          </div>";
    
    echo "</div></div>";
} else {
    echo "<div class='alert alert-warning'>
            <h5>无效请求</h5>
            <p>没有提交任何重复文章处理请求。</p>
            <p><a href='fix_duplicates.php' class='btn btn-primary'>返回修复工具</a></p>
          </div>";
}

$conn->close();
?>
</body>
</html>
