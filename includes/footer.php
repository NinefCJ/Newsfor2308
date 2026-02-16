<?php
// 页脚模块
// 注意：此文件应在适当位置被包含，通常在所有页面内容之后
?>
    </main> <!-- 关闭header.php中打开的main标签 -->
    
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="mb-3"><i class="bi bi-newspaper"></i> 2308班新闻中心</h5>
                    <p class="text-light small">
                        记录班级点滴，传递校园声音。<br>
                        这里是2308班官方新闻发布平台，由班级新闻部独立运营。
                    </p>
                    <div class="d-flex gap-3">
                        <a href="https://gitee.com/ninefyu" class="text-white" title="Gitee:NinefYu"><i class="bi bi-gitee fs-5"></i></a>
                        <a href="https://ninefcj.github.io/NinefCJ/" class="text-white" title="开发者个人主页"><i class="bi bi-qq fs-5"></i></a>
                        <a href="2395953343@qq.com" class="text-white" title="联系我们"><i class="bi bi-envelope fs-5"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                    <h6 class="mb-3">快速链接</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-light text-decoration-none">首页</a></li>
                        <li class="mb-2"><a href="about.php" class="text-light text-decoration-none">关于我们</a></li>
                        <li class="mb-2"><a href="admin/login.php" class="text-light text-decoration-none">管理后台</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h6 class="mb-3">新闻分类</h6>
                    <ul class="list-unstyled">
                        <?php
                        // 快速查询热门分类（只显示前4个）
                        $footer_cats_sql = "SELECT c.id, c.name FROM categories c 
                                           LEFT JOIN articles a ON c.id = a.category_id 
                                           GROUP BY c.id 
                                           ORDER BY COUNT(a.id) DESC 
                                           LIMIT 4";
                        $footer_cats_result = $conn->query($footer_cats_sql);
                        if ($footer_cats_result && $footer_cats_result->num_rows > 0) {
                            while($cat = $footer_cats_result->fetch_assoc()) {
                                echo '<li class="mb-2"><a href="category.php?id='.$cat['id'].'" class="text-light text-decoration-none">'.safe_output($cat['name']).'</a></li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
                
                <div class="col-lg-3">
                    <h6 class="mb-3">联系方式</h6>
                    <ul class="list-unstyled text-light small">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i> 2308班教室 · 新闻部</li>
                        <li class="mb-2"><i class="bi bi-clock me-2"></i> 每周二、四 16:00-17:30</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> news2308@your-school.edu</li>
                        <li class="mb-2"><i class="bi bi-people me-2"></i> 新闻部部长：张三同学</li>
                    </ul>
                </div>
            </div>
            
            <hr class="bg-light my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0 small">
                        &copy; <?php echo date('Y'); ?> 2308班新闻部. 保留所有权利.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 small">
                        <span class="text-light opacity-75">技术支持：</span>
                        <span class="text-info">PHP <?php echo PHP_VERSION; ?></span> · 
                        <span class="text-info">MySQL</span> · 
                        <span class="text-info">Bootstrap 5</span>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper (性能优化：放在body末尾) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
            crossorigin="anonymous"></script>
    
    <!-- 自定义JavaScript -->
    <script>
        // 简单的交互效果
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有卡片添加悬停效果
            const cards = document.querySelectorAll('.hover-shadow');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transition = 'box-shadow 0.3s ease';
                    card.style.boxShadow = '0 .5rem 1rem rgba(0,0,0,.15)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.boxShadow = '';
                });
            });
            
            // 回到顶部按钮
            const backToTop = document.createElement('button');
            backToTop.innerHTML = '<i class="bi bi-arrow-up"></i>';
            backToTop.className = 'btn btn-primary rounded-circle position-fixed bottom-3 end-3 d-none';
            backToTop.style.width = '50px';
            backToTop.style.height = '50px';
            backToTop.style.zIndex = '1000';
            backToTop.onclick = () => window.scrollTo({top: 0, behavior: 'smooth'});
            document.body.appendChild(backToTop);
            
            window.addEventListener('scroll', () => {
                backToTop.classList.toggle('d-none', window.scrollY < 300);
            });
        });
    </script>
</body>
</html>
