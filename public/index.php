<?php

require_once __DIR__ . '/../autoload.php';

use Iowen\GetFavicon\FaviconFetcher;

// 配置favicon获取器
$fetcher = new FaviconFetcher(
    __DIR__ . '/../cache',
    __DIR__ . '/../public/favicon.png',
    2592000, // 30天缓存
    [
        'debug' => false, // 开启调试模式
        'timeout' => 5
    ]
);

// 获取URL参数
$url = $_GET['url'] ?? '';
$refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

if (empty($url)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL parameter is required']);
    exit;
}

try {
    // 获取favicon
    $result = $fetcher->fetch($url, $refresh);
    
    // 设置响应头
    header('Content-Type: ' . $result['mime']);
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('X-Cache: ' . ($result['cached'] ? 'HIT' : 'MISS'));
    
    // 输出图标内容
    echo $result['content'];
    
} catch (\Exception $e) {
    error_log('Error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
} 