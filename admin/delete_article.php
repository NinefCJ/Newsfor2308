<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// 验证文章ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "无效的文章ID";
    header('Location: dashboard.php');
    exit();
}

$article_id = intval($_GET['id']);

// 获取文章信息（用于删除封面图片）
$sql = "SELECT cover_image FROM articles WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();
$stmt->close();

if ($article) {
    // 删除封面图片
    if ($article['cover_image'] && file_exists('../' . $article['cover_image'])) {
        @unlink('../' . $article['cover_image']);
    }
    
    // 删除文章
    $delete_sql = "DELETE FROM articles WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $article_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "文章删除成功";
    } else {
        $_SESSION['error'] = "删除失败: " . $conn->error;
    }
    $stmt->close();
} else {
    $_SESSION['error'] = "文章不存在";
}

header('Location: dashboard.php');
exit();
?>
