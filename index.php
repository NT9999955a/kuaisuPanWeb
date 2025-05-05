<?php
session_start();

// 配置
define('ROOT_DIR', realpath(__DIR__.'/files'));
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// 协议检测
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

// 基础路径处理
$host = explode(':', $_SERVER['HTTP_HOST'])[0];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_URL', $protocol . $host . $basePath);

// 路径验证
$currentDir = isset($_GET['dir']) ? trim($_GET['dir'], '/') : '';
$currentPath = realpath(ROOT_DIR.'/'.$currentDir);

if(!$currentPath || strpos($currentPath, ROOT_DIR) !== 0 || !is_dir($currentPath)) {
    header('Location: '.BASE_URL.'/?dir=');
    exit;
}

// 文件上传处理
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $uploadDir = ROOT_DIR.'/'.$currentDir;
    
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $_SESSION['upload_message'] = '目录创建失败';
        header('Location: '.BASE_URL.'/?dir='.urlencode($currentDir));
        exit;
    }

    $fileName = basename($_FILES['fileToUpload']['name']);
    $fileTmpName = $_FILES['fileToUpload']['tmp_name'];
    $fileSize = $_FILES['fileToUpload']['size'];
    $fileType = mime_content_type($fileTmpName);

    // 文件验证
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf', 'text/plain',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $uploadErrors = [];
    if ($fileSize > MAX_FILE_SIZE) {
        $uploadErrors[] = "文件大小超过50MB限制";
    }
    if (!in_array($fileType, $allowedTypes)) {
        $uploadErrors[] = "不支持的文件类型（仅允许：JPEG, PNG, GIF, PDF, TXT, DOCX）";
    }
    if ($fileTmpName === '') {
        $uploadErrors[] = "未选择文件";
    }
    if (isset($_FILES['fileToUpload']['error']) && $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors[] = "上传错误代码：".$_FILES['fileToUpload']['error'];
    }

    if (empty($uploadErrors)) {
        // 防止文件覆盖
        $newFileName = $fileName;
        $counter = 1;
        $nameParts = explode('.', $fileName);
        $ext = end($nameParts);
        
        while (file_exists($uploadDir.'/'.$newFileName)) {
            $baseName = implode('.', array_slice($nameParts, 0, -1));
            $newFileName = "{$baseName}_{$counter}.{$ext}";
            $counter++;
        }

        if (move_uploaded_file($fileTmpName, $uploadDir.'/'.$newFileName)) {
            $_SESSION['upload_message'] = "文件 '{$fileName}' 上传成功";
        } else {
            $_SESSION['upload_message'] = "文件保存失败";
        }
    } else {
        $_SESSION['upload_message'] = "上传失败：".implode(' ', $uploadErrors);
    }

    // PRG模式防止重复提交
    header('Location: '.BASE_URL.'/?dir='.urlencode($currentDir));
    exit;
}

// 文件下载处理
if(isset($_GET['file'])){
    // 安全路径验证
    $relativePath = ltrim($_GET['file'], '/');
    $filePath = realpath(ROOT_DIR.'/'.$relativePath);
    
    if($filePath && 
       is_file($filePath) && 
       strpos($filePath, ROOT_DIR) === 0 && 
       is_readable($filePath))
    {
        // 清空输出缓冲区
        while (ob_get_level()) ob_end_clean();
        
        // 设置下载头
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.rawurlencode(basename($filePath)).'"');
        header('Content-Length: '.filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        
        // 输出文件内容
        readfile($filePath);
        exit;
    }
    http_response_code(403);
    exit('禁止访问：'.htmlspecialchars($relativePath, ENT_QUOTES));
}

// 获取目录内容
$items = scandir($currentPath);
$folders = $files = [];
foreach($items as $item){
    if(in_array($item, ['.','..'])) continue;
    $fullPath = $currentPath.'/'.$item;
    is_dir($fullPath) ? $folders[] = $item : $files[] = $item;
}
sort($folders);
sort($files);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安全网盘系统</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .breadcrumb { color: #666; margin-bottom: 15px; }
        .file-list { list-style: none; padding: 0; }
        .file-item { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .folder { background: #f8f9fa; }
        .folder a { color: #0366d6; text-decoration: none; }
        .file a { color: #333; }
        .icon { margin-right: 8px; }
        .size { color: #666; font-size: 0.9em; margin-left: 10px; }
        
        /* 上传区域样式 */
        .upload-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .upload-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .upload-btn:hover { background: #218838; }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>安全网盘系统</h1>
        <div class="breadcrumb">
            当前位置：/<?= htmlspecialchars($currentDir) ?>
            <?php if($currentDir): ?>
                <a href="<?= BASE_URL ?>/?dir=<?= urlencode(dirname($currentDir)) ?>">[返回上级]</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 上传区域 -->
    <div class="upload-section">
        <h3>文件上传</h3>
        <form class="upload-form" method="post" enctype="multipart/form-data">
            <input type="file" name="fileToUpload" required>
            <button type="submit" class="upload-btn">上传文件</button>
        </form>
        <?php if(isset($_SESSION['upload_message'])): ?>
            <div class="message <?= strpos($_SESSION['upload_message'], '失败') !== false ? 'error' : 'success' ?>">
                <?= htmlspecialchars($_SESSION['upload_message']) ?>
            </div>
            <?php unset($_SESSION['upload_message']); ?>
        <?php endif; ?>
    </div>

    <!-- 文件列表 -->
    <ul class="file-list">
        <?php foreach($folders as $folder): ?>
            <li class="file-item folder">
                <span class="icon">📁</span>
                <a href="<?= BASE_URL ?>/?dir=<?= urlencode($currentDir.'/'.$folder) ?>">
                    <?= htmlspecialchars($folder) ?>
                </a>
            </li>
        <?php endforeach; ?>

        <?php foreach($files as $file): ?>
            <li class="file-item file">
                <span class="icon">📄</span>
                <a href="<?= BASE_URL ?>/?file=<?= urlencode($currentDir.'/'.$file) ?>">
                    <?= htmlspecialchars(basename($file)) ?>
                </a>
                <span class="size">
                    <?= formatSize(filesize("$currentPath/$file")) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- 格式化函数 -->
    <?php function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    } ?>
</body>
</html>
