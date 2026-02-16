<?php
// diagnose_articles.php - 文章重复问题诊断脚本
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>文章重复问题诊断</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .duplicate-table { font-size: 14px; }
        .duplicate-badge { font-size: 12px; }
        .danger { background-color: #f8d7da; }
        .warning { background-color: #fff3cd; }
        .success { background-color: #d1e7dd; }
    </style>
</head>
<body class='container mt-4'>
<h2 class='mb-4'>📊 文章重复问题诊断</h2>";

// 检查数据库连接
if (!$conn || $conn->connect_error) {
    die("<div class='alert alert-danger'>数据库连接失败: " . ($conn ? $conn->connect_error : '连接对象为空') . "</div>");
}

// 1. 检查文章总数
$sql = "SELECT COUNT(*) as total FROM articles";
$result = $conn->query($sql);
$total_articles = $result->fetch_assoc()['total'];

echo "<div class='card mb-4'>
        <div class='card-header'>数据库统计</div>
        <div class='card-body'>
            <p>文章总数: <strong>{$total_articles}</strong> 篇</p>
            <p>创建时间: " . date('Y-m-d H:i:s') . "</p>
        </div>
      </div>";

// 2. 检查完全重复的文章（标题和内容都相同）
echo "<div class='card mb-4'>
        <div class='card-header'>1. 检查完全重复的文章</div>
        <div class='card-body'>";

$sql = "SELECT title, content, COUNT(*) as count 
        FROM articles 
        GROUP BY title, content 
        HAVING count > 1
        ORDER BY count DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table class='table table-sm duplicate-table'>
            <thead class='table-dark'>
                <tr>
                    <th>文章标题</th>
                    <th>重复次数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>";
    
    while($row = $result->fetch_assoc()) {
        $title_short = mb_substr($row['title'], 0, 40, 'UTF-8') . (mb_strlen($row['title'], 'UTF-8') > 40 ? '...' : '');
        echo "<tr class='danger'>
                <td>{$title_short}</td>
                <td><span class='badge bg-danger duplicate-badge'>{$row['count']} 次</span></td>
                <td>
                    <button class='btn btn-sm btn-outline-danger' 
                            onclick=\"showDuplicateDetails('title_content', '{$conn->real_escape_string($row['title'])}')\">
                        查看详情
                    </button>
                </td>
              </tr>";
    }
    
    echo "</tbody></table>";
    echo "<p class='text-danger mt-3'><strong>发现完全重复的文章！建议立即清理。</strong></p>";
} else {
    echo "<p class='text-success'>✓ 未发现完全重复的文章（标题和内容都相同）</p>";
}

echo "</div></div>";

// 3. 检查标题重复的文章
echo "<div class='card mb-4'>
        <div class='card-header'>2. 检查标题重复的文章</div>
        <div class='card-body'>";

$sql = "SELECT title, COUNT(*) as count 
        FROM articles 
        GROUP BY title 
        HAVING count > 1
        ORDER BY count DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table class='table table-sm duplicate-table'>
            <thead class='table-dark'>
                <tr>
                    <th>文章标题</th>
                    <th>重复次数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>";
    
    while($row = $result->fetch_assoc()) {
        $title_short = mb_substr($row['title'], 0, 40, 'UTF-8') . (mb_strlen($row['title'], 'UTF-8') > 40 ? '...' : '');
        echo "<tr class='warning'>
                <td>{$title_short}</td>
                <td><span class='badge bg-warning duplicate-badge'>{$row['count']} 次</span></td>
                <td>
                    <button class='btn btn-sm btn-outline-warning' 
                            onclick=\"showDuplicateDetails('title_only', '{$conn->real_escape_string($row['title'])}')\">
                        查看详情
                    </button>
                </td>
              </tr>";
    }
    
    echo "</tbody></table>";
    echo "<p class='text-warning mt-3'><strong>发现标题重复的文章！可能需要清理。</strong></p>";
} else {
    echo "<p class='text-success'>✓ 未发现标题重复的文章</p>";
}

echo "</div></div>";

// 4. 检查置顶文章重复
echo "<div class='card mb-4'>
        <div class='card-header'>3. 检查置顶文章</div>
        <div class='card-body'>";

$sql = "SELECT id, title, publish_date 
        FROM articles 
        WHERE is_featured = 1 
        ORDER BY publish_date DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<p>置顶文章数量: <strong>{$result->num_rows}</strong> 篇</p>";
    echo "<table class='table table-sm'>
            <thead class='table-secondary'>
                <tr><th>ID</th><th>标题</th><th>发布时间</th></tr>
            </thead>
            <tbody>";
    
    $featured_titles = [];
    while($row = $result->fetch_assoc()) {
        $is_duplicate = in_array($row['title'], $featured_titles);
        $row_class = $is_duplicate ? 'warning' : '';
        
        echo "<tr class='{$row_class}'>
                <td>{$row['id']}</td>
                <td>{$row['title']}" . ($is_duplicate ? " <span class='badge bg-warning'>重复</span>" : "") . "</td>
                <td>{$row['publish_date']}</td>
              </tr>";
        
        $featured_titles[] = $row['title'];
    }
    
    echo "</tbody></table>";
} else {
    echo "<p class='text-muted'>暂无置顶文章</p>";
}

echo "</div></div>";

// 5. 最近发布的文章检查
echo "<div class='card mb-4'>
        <div class='card-header'>4. 最近发布的文章</div>
        <div class='card-body'>";

$sql = "SELECT id, title, publish_date 
        FROM articles 
        ORDER BY publish_date DESC 
        LIMIT 10";
$result = $conn->query($sql);

echo "<table class='table table-sm'>
        <thead class='table-secondary'>
            <tr><th>ID</th><th>标题</th><th>发布时间</th><th>状态</th></tr>
        </thead>
        <tbody>";

$recent_titles = [];
while($row = $result->fetch_assoc()) {
    $is_duplicate = in_array($row['title'], $recent_titles);
    $row_class = $is_duplicate ? 'danger' : '';
    
    echo "<tr class='{$row_class}'>
            <td>{$row['id']}</td>
            <td>{$row['title']}" . ($is_duplicate ? " <span class='badge bg-danger'>重复</span>" : "") . "</td>
            <td>{$row['publish_date']}</td>
            <td>" . ($is_duplicate ? "⚠ 重复" : "✓ 正常") . "</td>
          </tr>";
    
    $recent_titles[] = $row['title'];
}

echo "</tbody></table></div></div>";

// 6. 提供修复工具
echo "<div class='card mb-4'>
        <div class='card-header'>5. 修复工具</div>
        <div class='card-body'>
            <div class='row'>
                <div class='col-md-6 mb-3'>
                    <div class='card'>
                        <div class='card-body'>
                            <h5 class='card-title'>快速修复</h5>
                            <p class='card-text'>删除完全重复的文章（保留最早的一篇）</p>
                            <a href='fix_duplicates.php?action=quick_fix' class='btn btn-primary' onclick='return confirm(\"确定要删除重复文章吗？此操作不可逆！\")'>执行快速修复</a>
                        </div>
                    </div>
                </div>
                <div class='col-md-6 mb-3'>
                    <div class='card'>
                        <div class='card-body'>
                            <h5 class='card-title'>高级修复</h5>
                            <p class='card-text'>手动选择要保留的文章</p>
                            <a href='fix_duplicates.php' class='btn btn-outline-primary'>打开高级修复工具</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='mt-4'>
                <h5>预防措施</h5>
                <ul>
                    <li><strong>添加唯一索引：</strong>防止未来插入重复文章</li>
                    <li><strong>修改查询语句：</strong>使用 DISTINCT 或 GROUP BY</li>
                    <li><strong>修复代码逻辑：</strong>确保不重复输出</li>
                </ul>
                
                <form method='POST' action='fix_duplicates.php'>
                    <div class='form-check mb-2'>
                        <input class='form-check-input' type='checkbox' id='add_unique_index' name='add_unique_index'>
                        <label class='form-check-label' for='add_unique_index'>
                            为文章表添加唯一索引（防止标题重复）
                        </label>
                    </div>
                    <button type='submit' class='btn btn-success' name='prevent_duplicates'>执行预防措施</button>
                </form>
            </div>
        </div>
      </div>";

$conn->close();
?>

<script>
function showDuplicateDetails(type, title) {
    // 打开新窗口显示重复文章的详细信息
    var url = 'duplicate_details.php?type=' + type + '&title=' + encodeURIComponent(title);
    window.open(url, 'duplicateDetails', 'width=1000,height=600,scrollbars=yes');
}
</script>

<div class='alert alert-info'>
    <h5>诊断建议：</h5>
    <ol>
        <li>首先运行诊断，查看具体的重复情况</li>
        <li>如果发现完全重复的文章，建议使用"快速修复"</li>
        <li>如果只是标题重复，但内容不同，可能需要手动处理</li>
        <li>修复后，建议添加唯一索引以防止未来再次出现重复</li>
    </ol>
</div>

<p><a href='index.php' class='btn btn-secondary'>返回首页</a></p>
</body>
</html>
