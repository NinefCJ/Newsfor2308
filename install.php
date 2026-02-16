<?php
// install.php - 数据库安装脚本
// 警告：安装完成后请立即删除此文件！

// 安全验证：设置一个安装密码，防止未经授权的访问
$install_password = '2308news_install_2026';
$entered_password = $_GET['password'] ?? '';

if ($entered_password !== $install_password) {
    die('<h2>数据库安装</h2>
         <form method="GET">
             <p>请输入安装密码：</p>
             <input type="password" name="password">
             <input type="submit" value="开始安装">
         </form>
         <p><small>密码：2308news_install_2026</small></p>');
}

// 包含数据库配置
require_once 'includes/config.php';

echo '<!DOCTYPE html>
<html>
<head>
    <title>2308班新闻网站 - 数据库安装</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container">
    <h2 class="mb-4">📦 2308班新闻网站 - 数据库安装</h2>';

// 检查数据库连接
echo '<h4>1. 检查数据库连接...</h4>';
if ($conn->connect_error) {
    die('<div class="alert alert-danger">数据库连接失败: ' . $conn->connect_error . '</div>');
}
echo '<div class="alert alert-success">✓ 数据库连接成功</div>';

// 创建表的SQL语句
$sql_script = "
-- 1. 创建新闻分类表
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. 创建新闻文章表
CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `category_id` INT(11) DEFAULT NULL,
  `author` VARCHAR(100) DEFAULT '2308新闻部',
  `publish_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `view_count` INT(11) DEFAULT 0,
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category_date` (`category_id`,`publish_date`),
  KEY `idx_date` (`publish_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 创建管理员表
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// 分割SQL语句并执行
$queries = explode(';', $sql_script);
$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        echo '<p>执行: <code>' . htmlspecialchars(substr($query, 0, 80)) . '...</code></p>';
        
        if ($conn->query($query) === TRUE) {
            echo '<div class="alert alert-success">✓ 成功</div>';
            $success_count++;
        } else {
            echo '<div class="alert alert-warning">⚠ ' . $conn->error . '</div>';
            $error_count++;
        }
    }
}

// 插入初始数据
echo '<h4 class="mt-4">2. 插入初始数据...</h4>';

// 插入分类
$categories = ['班级动态', '校园热点', '通知公告', '学习心得', '比赛荣誉'];
foreach ($categories as $category) {
    $sql = "INSERT IGNORE INTO categories (name) VALUES ('" . $conn->real_escape_string($category) . "')";
    if ($conn->query($sql)) {
        echo '<div class="alert alert-success">✓ 添加分类: ' . $category . '</div>';
    }
}

// 插入默认管理员（密码：ChangeMe123）
$admin_sql = "INSERT IGNORE INTO admins (username, password_hash) VALUES 
              ('news2308', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')";
if ($conn->query($admin_sql)) {
    echo '<div class="alert alert-success">✓ 创建默认管理员账号</div>';
    echo '<div class="alert alert-info">用户名: <strong>news2308</strong><br>密码: <strong>ChangeMe123</strong></div>';
}

// 插入示例文章（可选）
echo '<h4 class="mt-4">3. 创建示例文章...</h4>';
$example_article = [
    'title' => '欢迎来到2308班新闻中心！',
    'content' => '<p>欢迎使用2308班新闻网站！这是您的第一篇新闻文章。</p>
                 <p>您可以登录后台管理系统，添加更多新闻内容。</p>
                 <p><strong>后台管理地址：</strong> <a href="admin/login.php">admin/login.php</a></p>
                 <p><strong>默认管理员账号：</strong></p>
                 <ul>
                     <li>用户名：news2308</li>
                     <li>密码：ChangeMe123</li>
                 </ul>
                 <p class="text-danger">请务必在首次登录后修改密码！</p>',
    'category_id' => 1,
    'author' => '系统管理员',
    'is_featured' => 1
];

$insert_sql = "INSERT INTO articles (title, content, category_id, author, is_featured) 
               VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("ssisi", 
    $example_article['title'],
    $example_article['content'],
    $example_article['category_id'],
    $example_article['author'],
    $example_article['is_featured']
);

if ($stmt->execute()) {
    echo '<div class="alert alert-success">✓ 创建示例文章成功</div>';
} else {
    echo '<div class="alert alert-warning">⚠ 示例文章创建失败: ' . $stmt->error . '</div>';
}
$stmt->close();

// 检查表是否创建成功
echo '<h4 class="mt-4">4. 验证数据库表...</h4>';
$tables = ['categories', 'articles', 'admins'];
$existing_tables = [];

$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
}

foreach ($tables as $table) {
    if (in_array($table, $existing_tables)) {
        echo '<div class="alert alert-success">✓ 表 `' . $table . '` 存在</div>';
    } else {
        echo '<div class="alert alert-danger">✗ 表 `' . $table . '` 不存在</div>';
    }
}

// 显示总结
echo '<hr>';
echo '<h3 class="mt-4">安装总结</h3>';
echo '<div class="alert alert-info">';
echo '<p>成功执行的SQL语句: ' . $success_count . '</p>';
echo '<p>有错误的SQL语句: ' . $error_count . '</p>';
echo '<p>数据库表总数: ' . count($existing_tables) . '</p>';

if (count($tables) === count(array_intersect($tables, $existing_tables))) {
    echo '<div class="alert alert-success mt-3"><h4>🎉 安装完成！</h4>';
    echo '<p>数据库表已成功创建，您现在可以：</p>';
    echo '<ol>';
    echo '<li><a href="index.php" target="_blank">访问网站首页</a></li>';
    echo '<li><a href="admin/login.php" target="_blank">登录后台管理系统</a></li>';
    echo '<li><strong>重要：</strong>立即删除此安装文件 (install.php)</li>';
    echo '</ol>';
    echo '<p><strong>管理员账号：</strong></p>';
    echo '<ul>';
    echo '<li>用户名: news2308</li>';
    echo '<li>密码: ChangeMe123</li>';
    echo '</ul>';
    echo '<p class="text-danger"><strong>警告：请务必在首次登录后修改密码！</strong></p>';
    echo '</div>';
} else {
    echo '<div class="alert alert-warning mt-3"><h4>⚠ 安装可能不完整</h4>';
    echo '<p>有些表可能没有创建成功，请检查上面的错误信息。</p>';
    echo '<p>您也可以尝试在phpMyAdmin中手动运行SQL脚本。</p>';
    echo '</div>';
}

echo '</div>'; // 结束alert

echo '</div></body></html>';

$conn->close();
?>
