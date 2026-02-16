<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php'; // 必须登录才能访问

$success = '';
$error = '';

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 获取并转义表单数据（防止SQL注入）
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']); // 富文本内容，包含HTML标签
    $category_id = intval($_POST['category_id']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $cover_image_path = null; // 初始化为null
    
    // --- 处理封面图片上传 ---
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['cover_image']['type'];
        
        // 验证文件类型
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../assets/uploads/';
            
            // 确保上传目录存在
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // 生成唯一文件名，防止冲突
            $file_extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $unique_filename = 'article_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $unique_filename;
            
            // 移动上传的文件
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_file)) {
                $cover_image_path = 'assets/uploads/' . $unique_filename; // 存入数据库的路径
            } else {
                $error = "封面上传失败，请检查文件夹权限。";
            }
        } else {
            $error = "只允许上传 JPG, PNG, GIF, WebP 格式的图片。";
        }
    } elseif (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] != 4) {
        // 错误码4表示没有文件上传，其他错误码需要处理
        $upload_errors = [
            1 => '上传的文件超过了 php.ini 中 upload_max_filesize 指令限制的大小。',
            2 => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值。',
            3 => '文件只有部分被上传。',
            6 => '找不到临时文件夹。',
            7 => '文件写入失败。',
            8 => 'PHP 扩展程序停止了文件上传。'
        ];
        $error_code = $_FILES['cover_image']['error'];
        $error = "图片上传错误: " . ($upload_errors[$error_code] ?? "未知错误 (代码: $error_code)");
    }
    
    // 如果没有任何错误，插入数据库
    if (empty($error)) {
        $sql = "INSERT INTO articles (title, content, category_id, author, cover_image, is_featured) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissi", $title, $content, $category_id, $author, $cover_image_path, $is_featured);
        
        if ($stmt->execute()) {
            $new_article_id = $stmt->insert_id;
            $success = "新闻发布成功！文章ID: $new_article_id。您可以 
                       <a href='edit_article.php?id=$new_article_id'>继续编辑</a> 或 
                       <a href='dashboard.php'>返回列表</a>。";
            
            // 清空表单（可选）
            $title = $content = $author = '';
            $category_id = '';
            $is_featured = 0;
        } else {
            $error = "数据库错误: " . $conn->error;
        }
        $stmt->close();
    }
}

// 查询所有分类，用于下拉选择框
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>发布新闻 - 2308新闻后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* 视频上传样式 */
        .video-upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .video-upload-area:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        
        .video-upload-area i {
            font-size: 3rem;
            color: #007bff;
            margin-bottom: 15px;
        }
        
        /* 视频预览 */
        .video-preview {
            max-width: 100%;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        /* 进度条动画 */
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        
        @keyframes progress-bar-stripes {
            0% { background-position: 1rem 0; }
            100% { background-position: 0 0; }
        }
        
        /* 编辑器样式 */
        .tox-tinymce {
            border: 1px solid #dee2e6 !important;
            border-radius: 0.375rem;
        }
        
        /* 图片预览容器 */
        #imagePreview {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        #previewImg {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- 导航栏 -->
        <nav class="navbar navbar-dark bg-primary mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left"></i> 返回</a>
                <span class="navbar-text text-light">发布新闻文章</span>
            </div>
        </nav>
        
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-white">
                        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> 发布新文章</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data" id="articleForm">
                            <div class="row">
                                <!-- 左侧：主要内容 -->
                                <div class="col-lg-8">
                                    <div class="mb-4">
                                        <label for="title" class="form-label fw-bold">文章标题 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                               value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                                               placeholder="请输入新闻标题" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="newsContent" class="form-label fw-bold">文章正文 <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="newsContent" name="content" rows="12"><?php echo isset($content) ? htmlspecialchars($content) : ''; ?></textarea>
                                        <div class="form-text mt-2">
                                            使用上方工具栏进行格式化。支持插入图片、链接、列表、视频等。
                                        </div>
                                        
                                        <!-- 图片上传进度条 -->
                                        <div id="uploadProgress" class="mt-3" style="display:none;">
                                            <div class="progress" style="height: 20px;">
                                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 0%;">0%</div>
                                            </div>
                                            <p class="text-center small mt-1" id="progressText">正在上传...</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 右侧：设置选项 -->
                                <div class="col-lg-4">
                                    <!-- 发布设置卡片 -->
                                    <div class="card border-primary mb-4">
                                        <div class="card-header bg-primary text-white">
                                            <i class="bi bi-gear"></i> 发布设置
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="bi bi-check2-circle"></i> 立即发布
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('articleForm').reset();">
                                                    <i class="bi bi-x-circle"></i> 清空表单
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 文章属性卡片 -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="bi bi-tags"></i> 文章属性
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="category_id" class="form-label">分类 <span class="text-danger">*</span></label>
                                                <select name="category_id" id="category_id" class="form-select" required>
                                                    <option value="">-- 请选择分类 --</option>
                                                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                                        <?php while($cat = $categories_result->fetch_assoc()): ?>
                                                        <option value="<?php echo $cat['id']; ?>" 
                                                            <?php echo (isset($category_id) && $category_id == $cat['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                        <?php endwhile; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="author" class="form-label">作者/发布者</label>
                                                <input type="text" class="form-control" id="author" name="author" 
                                                       value="<?php echo isset($author) ? htmlspecialchars($author) : '2308新闻部'; ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" 
                                                           id="is_featured" name="is_featured" value="1" 
                                                           <?php echo (isset($is_featured) && $is_featured == 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_featured">
                                                        设为置顶文章
                                                    </label>
                                                </div>
                                                <div class="form-text">置顶文章将显示在网站首页最顶部</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 封面图片卡片 -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <i class="bi bi-image"></i> 封面图片
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="cover_image" class="form-label">上传封面</label>
                                                <input class="form-control" type="file" id="cover_image" name="cover_image" accept="image/*">
                                                <div class="form-text">
                                                    支持 JPG, PNG, GIF, WebP 格式。<br>
                                                    建议尺寸：1200×630 像素。<br>
                                                    最大文件大小：5MB。
                                                </div>
                                            </div>
                                            
                                            <div id="imagePreview" class="text-center mt-3" style="display:none;">
                                                <img id="previewImg" src="#" alt="图片预览" class="img-fluid rounded border" style="max-height: 150px;">
                                                <p class="small text-muted mt-2">预览</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 多媒体上传卡片 -->
                                    <div class="card border-info mb-4">
                                        <div class="card-header bg-info text-white">
                                            <i class="bi bi-film"></i> 插入多媒体
                                        </div>
                                        <div class="card-body">
                                            <p class="small mb-2">在正文编辑器中，可以点击工具栏的图片或视频按钮插入多媒体内容</p>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-info btn-sm" onclick="openVideoUpload()">
                                                    <i class="bi bi-camera-video"></i> 上传视频
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 操作指南 -->
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-info-circle"></i> 发布指南
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>带 <span class="text-danger">*</span> 的字段为必填项。</li>
                            <li>在正文编辑器中使用工具栏进行排版，支持插入图片和视频。</li>
                            <li>点击工具栏的"图片"按钮插入图片，点击"媒体"按钮插入视频。</li>
                            <li>封面图片不是必须的，但能提升文章吸引力。</li>
                            <li>支持直接拖放图片和视频到编辑器中进行上传。</li>
                            <li>发布前请仔细检查标题和内容。</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 视频上传模态框 -->
    <div class="modal fade" id="videoUploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-camera-video"></i> 上传视频</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- 拖放上传区域 -->
                    <div id="videoDropArea" class="video-upload-area" 
                         ondragover="event.preventDefault()" 
                         ondrop="handleVideoDrop(event)">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <h5>拖放视频文件到这里</h5>
                        <p class="text-muted">或点击选择文件</p>
                        <p class="text-muted small">支持 MP4, WebM, OGG 等格式，最大 50MB</p>
                        <button type="button" class="btn btn-primary mt-2" onclick="$('#videoFileInput').click()">
                            选择视频文件
                        </button>
                    </div>
                    
                    <input type="file" id="videoFileInput" accept="video/*" style="display:none;" 
                           onchange="handleVideoSelect(this.files[0])">
                    
                    <!-- 视频预览 -->
                    <div id="videoPreview" class="mt-3" style="display:none;">
                        <h6>视频预览</h6>
                        <video id="previewVideo" controls class="video-preview"></video>
                        <div class="mt-2">
                            <strong>文件名:</strong> <span id="videoName"></span><br>
                            <strong>大小:</strong> <span id="videoSize"></span><br>
                            <strong>格式:</strong> <span id="videoFormat"></span>
                        </div>
                    </div>
                    
                    <!-- 视频信息表单 -->
                    <div class="mt-3">
                        <label for="videoTitle" class="form-label">视频标题（可选）</label>
                        <input type="text" class="form-control" id="videoTitle" placeholder="输入视频标题">
                        
                        <label for="videoDescription" class="form-label">视频描述（可选）</label>
                        <textarea class="form-control" id="videoDescription" rows="2" placeholder="输入视频描述"></textarea>
                    </div>
                    
                    <!-- 上传选项 -->
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="autoInsert" checked>
                        <label class="form-check-label" for="autoInsert">
                            上传完成后自动插入到编辑器
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="uploadVideoBtn" onclick="uploadVideo()" disabled>
                        开始上传
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- TinyMCE 富文本编辑器 -->
    <script src="https://cdn.tiny.cloud/1/ol8tl2ctgfq9loqm4og7kr7m3tx0i2sio71smnby2s20gue8/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    // 初始化TinyMCE
    tinymce.init({
        selector: '#newsContent',
        height: 600,
        
        // 插件
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'media',
            'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks',
            'code', 'fullscreen', 'insertdatetime', 'media',
            'table', 'code', 'help', 'wordcount', 'emoticons',
            'quickbars', 'autoresize'
        ],
        
        // 工具栏
        toolbar: 'undo redo | blocks | ' +
                 'bold italic underline strikethrough | ' +
                 'alignleft aligncenter alignright alignjustify | ' +
                 'bullist numlist outdent indent | ' +
                 'link image media | ' +
                 'removeformat | code | help',
        
        // 媒体插件配置
        media_live_embeds: true,
        media_poster: false,
        media_alt_source: false,
        media_dimensions: false,
        
        // 文件上传处理
        file_picker_types: 'image media',
        file_picker_callback: function(callback, value, meta) {
            if (meta.filetype === 'image') {
                // 创建隐藏的文件输入框用于图片上传
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');
                
                input.onchange = function() {
                    const file = this.files[0];
                    const reader = new FileReader();
                    
                    // 显示上传进度
                    $('#uploadProgress').show();
                    $('#progressText').text('正在上传图片...');
                    
                    reader.onload = function(e) {
                        // 先插入临时图片
                        callback(e.target.result, {alt: file.name});
                        
                        // 上传图片到服务器
                        uploadImageToServer(file, e.target.result);
                    };
                    reader.readAsDataURL(file);
                };
                
                input.click();
            } else if (meta.filetype === 'media') {
                // 打开视频上传模态框
                $('#videoUploadModal').modal('show');
                
                // 监听上传完成
                $(document).on('videoUploadComplete', function(e, data) {
                    const videoHtml = `
                        <div class="video-container" style="margin: 20px 0;">
                            <video controls width="100%" style="max-width: 100%;">
                                <source src="${data.url}" type="video/mp4">
                                您的浏览器不支持视频标签
                            </video>
                        </div>
                    `;
                    callback(videoHtml, {});
                });
            }
        },
        
        // 图片上传配置
        images_upload_url: 'upload_image.php',
        images_upload_handler: function(blobInfo, progress) {
            return new Promise(function(resolve, reject) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_image.php');
                
                xhr.upload.onprogress = function(e) {
                    progress(e.loaded / e.total * 100);
                };
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.location) {
                            resolve(response.location);
                        } else {
                            reject('上传失败');
                        }
                    } else {
                        reject('HTTP错误: ' + xhr.status);
                    }
                };
                
                xhr.onerror = function() {
                    reject('上传失败');
                };
                
                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                
                xhr.send(formData);
            });
        },
        
        // 内容样式
        content_style: `
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                font-size: 16px; 
                line-height: 1.6;
                padding: 20px;
            }
            img { 
                max-width: 100%; 
                height: auto; 
                display: block;
                margin: 10px 0;
            }
            video { 
                max-width: 100%; 
                height: auto; 
                display: block;
                margin: 20px 0;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0;
            }
            table, th, td { 
                border: 1px solid #ddd; 
                padding: 8px; 
            }
            th { 
                background-color: #f5f5f5; 
            }
            blockquote { 
                border-left: 4px solid #007bff; 
                padding-left: 15px; 
                margin-left: 0; 
                color: #666; 
            }
        `,
        
        // 其他设置
        menubar: 'file edit view insert format tools table help',
        branding: false,
        promotion: false,
        autoresize_bottom_margin: 50,
        convert_urls: false
    });
    
    // 上传图片到服务器
    function uploadImageToServer(file, dataUrl) {
        const formData = new FormData();
        formData.append('file', file);
        
        $.ajax({
            url: 'upload_image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $('#progressBar').css('width', percentComplete + '%').text(percentComplete + '%');
                        $('#progressText').text('上传进度: ' + percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(data) {
                $('#uploadProgress').hide();
                if (data.location) {
                    console.log('图片已保存到服务器:', data.location);
                }
            },
            error: function(error) {
                $('#uploadProgress').hide();
                console.error('图片上传失败:', error);
                alert('图片上传失败，请稍后重试');
            }
        });
    }
    
    // 视频上传相关函数
    let selectedVideoFile = null;
    
    // 处理文件选择
    function handleVideoSelect(file) {
        if (!file) return;
        
        if (!file.type.startsWith('video/')) {
            alert('请选择视频文件');
            return;
        }
        
        selectedVideoFile = file;
        previewVideo(file);
        $('#uploadVideoBtn').prop('disabled', false);
    }
    
    // 处理拖放
    function handleVideoDrop(e) {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        handleVideoSelect(file);
    }
    
    // 预览视频
    function previewVideo(file) {
        const videoUrl = URL.createObjectURL(file);
        const preview = document.getElementById('previewVideo');
        const videoName = document.getElementById('videoName');
        const videoSize = document.getElementById('videoSize');
        const videoFormat = document.getElementById('videoFormat');
        
        preview.src = videoUrl;
        videoName.textContent = file.name;
        videoSize.textContent = formatBytes(file.size);
        videoFormat.textContent = file.type;
        
        $('#videoPreview').show();
    }
    
    // 格式化字节大小
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // 上传视频
    function uploadVideo() {
        if (!selectedVideoFile) return;
        
        const formData = new FormData();
        formData.append('video', selectedVideoFile);
        formData.append('title', $('#videoTitle').val());
        formData.append('description', $('#videoDescription').val());
        
        const uploadBtn = $('#uploadVideoBtn');
        uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> 上传中...');
        
        $.ajax({
            url: 'upload_video.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percent = Math.round((evt.loaded / evt.total) * 100);
                        uploadBtn.text('上传中 ' + percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    if ($('#autoInsert').is(':checked')) {
                        // 插入视频到TinyMCE编辑器
                        const videoHtml = `<div class="video-container">
                            <video controls width="100%" poster="${response.thumb}">
                                <source src="${response.url}" type="video/${selectedVideoFile.type.split('/')[1]}">
                                您的浏览器不支持视频标签
                            </video>
                            <p class="video-caption text-muted small">${selectedVideoFile.name}</p>
                        </div>`;
                        
                        tinymce.activeEditor.execCommand('mceInsertContent', false, videoHtml);
                    }
                    
                    alert('视频上传成功！');
                    $('#videoUploadModal').modal('hide');
                    resetVideoForm();
                } else {
                    alert('上传失败: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('上传失败: ' + error);
            },
            complete: function() {
                uploadBtn.prop('disabled', false).text('开始上传');
            }
        });
    }
    
    // 重置表单
    function resetVideoForm() {
        selectedVideoFile = null;
        $('#videoPreview').hide();
        $('#videoTitle').val('');
        $('#videoDescription').val('');
        $('#videoFileInput').val('');
        $('#uploadVideoBtn').prop('disabled', true);
    }
    
    // 打开视频上传模态框
    function openVideoUpload() {
        resetVideoForm();
        $('#videoUploadModal').modal('show');
    }
    
    // 封面图片预览
    document.getElementById('cover_image').addEventListener('change', function(event) {
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const file = event.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });
    
    // 表单提交前简单验证
    document.getElementById('articleForm').addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        const category = document.getElementById('category_id').value;
        
        if (!title) {
            alert('请填写文章标题！');
            e.preventDefault();
            return false;
        }
        
        if (!category) {
            alert('请选择文章分类！');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    </script>
</body>
</html>
