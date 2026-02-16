<?php
// emergency_fix.php - 紧急修复重复分类问题
require_once 'includes/config.php';

echo "<h2>紧急修复 - 分类重复问题</h2>";

// 1. 首先检查当前数据库中分类的真实情况
$sql = "SELECT * FROM categories ORDER BY id";
$result = $conn->query($sql);

echo "<h3>当前数据库中的分类记录：</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>名称</th><th>操作</th></tr>";

$categories_by_name = [];
$duplicate_ids = [];

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    
    // 检查是否有重复
    if (isset($categories_by_name[$row['name']])) {
        echo "<td style='color:red'>重复 - 建议删除</td>";
        $duplicate_ids[] = $row['id'];
    } else {
        echo "<td style='color:green'>正常</td>";
        $categories_by_name[$row['name']] = $row['id'];
    }
    
    echo "</tr>";
}

echo "</table>";

// 2. 一键修复重复分类
echo "<h3>一键修复选项</h3>";

if (isset($_GET['action']) && $_GET['action'] == 'fix') {
    echo "<h4>正在修复...</h4>";
    
    // 方法1：删除重复分类（保留id最小的）
    $sql = "DELETE c1 FROM categories c1
            INNER JOIN categories c2 
            WHERE c1.id > c2.id AND c1.name = c2.name";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✓ 已删除重复分类</p>";
        
        // 重新计数
        $result = $conn->query("SELECT COUNT(*) as count FROM categories");
        $row = $result->fetch_assoc();
        echo "<p>剩余分类数: " . $row['count'] . "</p>";
    } else {
        echo "<p style='color:red'>✗ 修复失败: " . $conn->error . "</p>";
    }
}

// 3. 提供修复链接
echo "<p><a href='?action=fix' style='background:red; color:white; padding:10px; display:inline-block;'>点击这里一键修复重复分类</a></p>";

// 4. 检查文章表中的分类引用
echo "<h3>检查文章分类引用</h3>";
$sql = "SELECT a.id, a.title, c.name as category_name, a.category_id
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        ORDER BY a.category_id";
$result = $conn->query($sql);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>文章ID</th><th>标题</th><th>分类ID</th><th>分类名称</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars(substr($row['title'], 0, 30)) . "...</td>";
    echo "<td>" . $row['category_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// 5. 简化分类数据
echo "<h3>重新生成标准分类</h3>";
echo "<p>如果问题持续存在，可以重置分类：</p>";
echo "<pre>
1. 班级动态
2. 校园热点  
3. 通知公告
4. 学习心得
5. 比赛荣誉
</pre>";

echo "<p><a href='?action=reset' style='background:orange; color:white; padding:10px;'>重置为标准分类</a></p>";

$conn->close();

echo "<hr><h3>后续步骤</h3>";
echo "<ol>";
echo "<li>运行上述修复后，刷新首页查看效果</li>";
echo "<li>如果问题仍存在，检查数据库是否还有重复记录</li>";
echo "<li>检查代码中是否有重复的包含或循环</li>";
echo "</ol>";
?>
