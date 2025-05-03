<?php
// 配置文件
define('ROOT_DIR', realpath(__DIR__.'/files'));  // 网盘根目录

// 自动检测协议（HTTP/HTTPS）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

// 获取纯净域名（自动去除端口）
$host = isset($_SERVER['HTTP_HOST']) ? 
        explode(':', $_SERVER['HTTP_HOST'])[0] : 
        $_SERVER['SERVER_NAME'];

// 生成基础URL（自动处理子目录）
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_URL', $protocol . $host . $basePath);

// 安全获取当前目录
$currentDir = isset($_GET['dir']) ? trim($_GET['dir'], '/') : '';
$currentPath = realpath(ROOT_DIR.'/'.$currentDir);

// 验证目录合法性
if(!$currentPath || strpos($currentPath, ROOT_DIR) !== 0 || !is_dir($currentPath)){
    header('Location: '.BASE_URL.'/?dir=');
    exit;
}

// 处理文件下载
if(isset($_GET['file'])){
    // 安全获取文件路径
    $relativePath = ltrim($_GET['file'], '/');
    $filePath = realpath(ROOT_DIR . '/' . $relativePath);
    
    // 增强验证逻辑
    if($filePath && 
       is_file($filePath) && 
       strpos($filePath, ROOT_DIR) === 0 && 
       is_readable($filePath))
    {
        // 设置下载头
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
        header('Content-Length: ' . filesize($filePath));
        
        // 高效输出文件
        readfile($filePath);
        exit;
    }
    http_response_code(403);
    exit('禁止访问：' . htmlspecialchars($relativePath, ENT_QUOTES));
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
    <title>PHP网盘系统</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .breadcrumb { color: #666; margin-bottom: 15px; }
        .file-list { list-style: none; padding: 0; }
        .file-item { padding: 10px; border: 1px solid #eee; margin-bottom: 5px; border-radius: 4px; }
        .folder { background: #f8f9fa; }
        .folder a { color: #0366d6; text-decoration: none; }
        .file a { color: #333; }
        .icon { margin-right: 8px; }
        .size { color: #666; font-size: 0.9em; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>文件管理器</h1>
        <div class="breadcrumb">
            当前位置：/<?= htmlspecialchars($currentDir) ?>
            <?php if($currentDir): ?>
                <a href="<?= BASE_URL ?>/?dir=<?= urlencode(dirname($currentDir)) ?>">[返回上级]</a>
            <?php endif; ?>
        </div>
    </div>

    <ul class="file-list">
        <?php foreach($folders as $folder): ?>
            <li class="file-item folder">
                <span class="icon">📁</span>
                <a href="<?= BASE_URL ?>/?dir=<?= urlencode($currentDir ? "$currentDir/$folder" : $folder) ?>">
                    <?= htmlspecialchars($folder) ?>
                </a>
            </li>
        <?php endforeach; ?>

        <?php foreach($files as $file): ?>
            <li class="file-item file">
                <span class="icon">📄</span>
                <a href="<?= BASE_URL ?>/?file=<?= urlencode($currentDir ? "$currentDir/$file" : $file) ?>">
                    <?= htmlspecialchars($file) ?>
                </a>
                <span class="size">
                    <?= formatSize(filesize("$currentPath/$file")) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>

<?php
// 格式化文件大小
function formatSize($bytes){
    $units = ['B','KB','MB','GB','TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes ? floor(log($bytes)/log(1024)) : 0;
    return round($bytes/pow(1024,$pow),2).' '.$units[$pow];
}
?>