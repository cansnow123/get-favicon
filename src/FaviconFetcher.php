<?php

namespace Iowen\GetFavicon;

class FaviconFetcher
{
    private const SUPPORTED_FORMATS = ['ico', 'png', 'jpg', 'jpeg', 'svg', 'gif'];
    private const DEFAULT_CACHE_TTL = 2592000; // 30 days
    
    private string $cacheDir;
    private string $defaultIcon;
    private int $cacheTtl;
    private array $options;
    private array $proxies;
    private array $proxyStats = []; // 代理服务器状态统计
    private array $domainConfig;    // 域名配置
    private array $proxyConfig;     // 代理配置
    private SvgGenerator $svgGenerator; // SVG生成器
    private int $cleanupTtl = 2592000; // 30天的缓存清理时间（秒）
    
    /**
     * 构造函数
     *
     * @param string $cacheDir 缓存目录
     * @param string $defaultIcon 默认图标路径
     * @param int $cacheTtl 缓存时间（秒）
     * @param array $options 其他选项
     * @param array $proxies 代理服务器配置
     */
    public function __construct(
        string $cacheDir = 'cache',
        string $defaultIcon = 'favicon.png',
        int $cacheTtl = self::DEFAULT_CACHE_TTL,
        array $options = [],
        array $proxies = []
    ) {
        $this->cacheDir = $cacheDir;
        $this->defaultIcon = $defaultIcon;
        $this->cacheTtl = $cacheTtl;
        
        // 加载配置文件
        $this->domainConfig = require __DIR__ . '/../config/domains.php';
        $this->proxyConfig = require __DIR__ . '/../config/proxies.php';
        
        // 合并选项
        $this->options = array_merge([
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'debug' => false,
            'max_retries' => 2,  // 最大重试次数
            'retry_delay' => 1,  // 重试延迟（秒）
            'cleanup_ttl' => 2592000, // 30天的缓存清理时间
        ], $options);
        
        // 设置代理配置
        $this->proxies = array_merge($this->proxyConfig['servers'], $proxies);
        
        // 初始化SVG生成器
        $this->svgGenerator = new SvgGenerator();
        
        $this->ensureCacheDirectoryExists();
        
        // 执行缓存清理
        $this->cleanupCache();
    }
    
    /**
     * 清理过期缓存文件
     *
     * @return void
     */
    private function cleanupCache(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $cleanupTtl = $this->options['cleanup_ttl'] ?? $this->cleanupTtl;
        $now = time();

        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = filemtime($file);
                if ($now - $fileTime > $cleanupTtl) {
                    unlink($file);
                    if ($this->options['debug']) {
                        error_log("Deleted expired cache file: " . $file);
                    }
                }
            }
        }
    }

    /**
     * 手动触发缓存清理
     *
     * @return void
     */
    public function cleanup(): void
    {
        $this->cleanupCache();
    }
    
    /**
     * 判断域名是否为中国大陆域名
     *
     * @param string $host
     * @return bool
     */
    private function isChineseDomain(string $host): bool
    {
        // 1. 检查白名单
        foreach ($this->domainConfig['cn_whitelist'] as $domain) {
            if (strcasecmp($host, $domain) === 0 || strcasecmp(substr($host, -strlen($domain) - 1), '.' . $domain) === 0) {
                return true;
            }
        }
        
        // 2. 检查黑名单
        foreach ($this->domainConfig['global_whitelist'] as $domain) {
            if (strcasecmp($host, $domain) === 0 || strcasecmp(substr($host, -strlen($domain) - 1), '.' . $domain) === 0) {
                return false;
            }
        }
        
        // 3. 检查域名后缀
        foreach ($this->domainConfig['cn_suffixes'] as $suffix) {
            if (strcasecmp(substr($host, -strlen($suffix)), $suffix) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取可用的代理配置
     *
     * @param string $type 代理类型（cn/global）
     * @return array|null
     */
    private function getAvailableProxy(string $type): ?array
    {
        if (!isset($this->proxies[$type])) {
            return null;
        }

        $availableProxies = [];
        foreach ($this->proxies[$type] as $proxy) {
            $proxyName = $proxy['name'];
            $stats = $this->proxyStats[$proxyName] ?? [
                'fails' => 0,
                'last_fail' => 0,
                'last_check' => 0
            ];

            // 检查代理是否可用
            if ($stats['fails'] >= $this->options['proxy_fail_threshold']) {
                // 检查是否已经过了恢复时间
                if (time() - $stats['last_fail'] < $this->options['proxy_recovery_time']) {
                    continue;
                }
                // 重置失败计数
                $stats['fails'] = 0;
            }

            // 检查代理状态
            if (time() - $stats['last_check'] > $this->options['proxy_check_interval']) {
                if ($this->checkProxy($proxy)) {
                    $stats['last_check'] = time();
                    $stats['fails'] = 0;
                } else {
                    $stats['fails']++;
                    $stats['last_fail'] = time();
                    continue;
                }
            }

            $this->proxyStats[$proxyName] = $stats;
            $availableProxies[] = $proxy;
        }

        if (empty($availableProxies)) {
            return null;
        }

        // 根据权重随机选择代理
        $totalWeight = array_sum(array_column($availableProxies, 'weight'));
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($availableProxies as $proxy) {
            $currentWeight += $proxy['weight'];
            if ($random <= $currentWeight) {
                return $proxy;
            }
        }

        return $availableProxies[0];
    }

    /**
     * 检查代理是否可用
     *
     * @param array $proxy
     * @return bool
     */
    private function checkProxy(array $proxy): bool
    {
        $proxyType = isset($this->proxies['cn'][$proxy['name']]) ? 'cn' : 'global';
        $testUrl = $this->proxyConfig['health_check']['test_url'][$proxyType];
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->proxyConfig['health_check']['timeout'],
                'proxy' => $proxy['http'] ?? $proxy['https'],
                'request_fulluri' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $result = @file_get_contents($testUrl, false, $context);
        return $result !== false;
    }

    /**
     * 获取合适的代理配置
     *
     * @param string $url
     * @return array|null
     */
    private function getProxyConfig(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $isChinese = $this->isChineseDomain($host);
        
        // 根据配置决定是否使用代理
        if ($isChinese && !$this->proxyConfig['strategy']['use_proxy_for_cn']) {
            return null;
        }
        if (!$isChinese && !$this->proxyConfig['strategy']['use_proxy_for_global']) {
            return null;
        }
        
        $proxyType = $isChinese ? 'cn' : 'global';
        return $this->getAvailableProxy($proxyType);
    }

    /**
     * 记录代理失败
     *
     * @param array $proxy
     */
    private function recordProxyFailure(array $proxy): void
    {
        $proxyName = $proxy['name'];
        if (!isset($this->proxyStats[$proxyName])) {
            $this->proxyStats[$proxyName] = [
                'fails' => 0,
                'last_fail' => 0,
                'last_check' => 0
            ];
        }

        $this->proxyStats[$proxyName]['fails']++;
        $this->proxyStats[$proxyName]['last_fail'] = time();
    }

    /**
     * 创建HTTP上下文
     *
     * @param string $url
     * @param array $headers
     * @param array|null $proxy
     * @return mixed
     */
    private function createContext(string $url, array $headers = [], ?array $proxy = null): mixed
    {
        $proxyConfig = $proxy ? $proxy : $this->getProxyConfig($url);
        $contextOptions = [
            'http' => [
                'timeout' => $this->options['timeout'],
                'user_agent' => $this->options['user_agent'],
                'header' => array_merge([
                    'Accept: image/webp,image/*,*/*;q=0.8',
                    'Connection: close'
                ], $headers)
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        // 添加代理配置
        if ($proxyConfig) {
            if ($proxyConfig['http']) {
                $contextOptions['http']['proxy'] = $proxyConfig['http'];
            }
            if ($proxyConfig['https']) {
                $contextOptions['ssl']['proxy'] = $proxyConfig['https'];
            }
        }

        return stream_context_create($contextOptions);
    }

    /**
     * 带重试的HTTP请求
     *
     * @param string $url
     * @param array $headers
     * @return string|false
     */
    private function fetchWithRetry(string $url, array $headers = []): string|false
    {
        $retries = 0;
        $lastError = null;
        $usedProxies = [];
        
        while ($retries <= $this->options['max_retries']) {
            try {
                $proxy = $this->getProxyConfig($url);
                if ($proxy) {
                    // 避免重复使用同一个代理
                    if (in_array($proxy['name'], $usedProxies)) {
                        continue;
                    }
                    $usedProxies[] = $proxy['name'];
                }

                $context = $this->createContext($url, $headers, $proxy);
                $content = @file_get_contents($url, false, $context);
                
                if ($content !== false) {
                    return $content;
                }
                
                if ($proxy) {
                    $this->recordProxyFailure($proxy);
                }
                
                $lastError = error_get_last();
                if ($this->options['debug']) {
                    error_log("Attempt {$retries} failed for {$url}: " . ($lastError['message'] ?? 'Unknown error'));
                }
                
                $retries++;
                if ($retries <= $this->options['max_retries']) {
                    sleep($this->options['retry_delay']);
                }
            } catch (\Exception $e) {
                if ($proxy) {
                    $this->recordProxyFailure($proxy);
                }
                
                $lastError = $e;
                if ($this->options['debug']) {
                    error_log("Exception on attempt {$retries} for {$url}: " . $e->getMessage());
                }
                
                $retries++;
                if ($retries <= $this->options['max_retries']) {
                    sleep($this->options['retry_delay']);
                }
            }
        }
        
        if ($this->options['debug'] && $lastError) {
            error_log("All retry attempts failed for {$url}: " . 
                ($lastError instanceof \Exception ? $lastError->getMessage() : $lastError['message'] ?? 'Unknown error'));
        }
        
        return false;
    }
    
    /**
     * 获取默认图标
     *
     * @param string|null $host 域名
     * @return array{content: string, mime: string, cached: bool}
     * @throws \Exception
     */
    private function getDefaultIcon(?string $host = null): array
    {
        // 如果host为空，使用默认值
        if (empty($host)) {
            $host = 'default';
        }
        
        // 生成动态SVG图标
        $content = $this->svgGenerator->generate($host);
        
        return [
            'content' => $content,
            'mime' => 'image/svg+xml',
            'cached' => false
        ];
    }

    /**
     * 获取网站的favicon
     *
     * @param string $url 网站URL
     * @param bool $refresh 是否强制刷新缓存
     * @return array{content: string, mime: string, cached: bool}
     * @throws \Exception
     */
    public function fetch(string $url, bool $refresh = false): array
    {
        try {
            $url = $this->normalizeUrl($url);
            $host = parse_url($url, PHP_URL_HOST);
            
            // 如果无法解析host，使用URL作为备用
            if (empty($host)) {
                $host = $url;
            }
            
            $cacheFile = $this->getCacheFile($url);
            
            // 检查缓存
            if (!$refresh) {
                // 尝试读取缓存文件（不指定扩展名）
                $cacheFiles = glob($cacheFile . '.*');
                if (!empty($cacheFiles)) {
                    $cacheFile = $cacheFiles[0];
                    $content = @file_get_contents($cacheFile);
                    if ($content !== false) {
                        $mime = $this->getMimeType($content);
                        // 如果是SVG，直接返回
                        if ($mime === 'image/svg+xml') {
                            return [
                                'content' => $content,
                                'mime' => $mime,
                                'cached' => true
                            ];
                        }
                        // 对于其他格式，如果不是PNG，尝试转换
                        if ($mime !== 'image/png') {
                            $converted = $this->convertToPng($content, $mime);
                            if ($converted) {
                                // 保存转换后的PNG
                                $pngFile = $cacheFile . '.png';
                                @file_put_contents($pngFile, $converted);
                                // 删除原缓存文件
                                @unlink($cacheFile);
                                return [
                                    'content' => $converted,
                                    'mime' => 'image/png',
                                    'cached' => true
                                ];
                            }
                        }
                        // 如果是PNG或转换失败，直接返回原内容
                        return [
                            'content' => $content,
                            'mime' => $mime,
                            'cached' => true
                        ];
                    }
                }
            }
            
            // 获取新的图标
            $result = $this->fetchFromUrl($url);
            
            // 保存到缓存
            if ($result['content']) {
                // 根据MIME类型决定文件扩展名
                $extension = $this->getExtensionFromMime($result['mime']);
                $cacheFile = $this->getCacheFile($url) . '.' . $extension;
                
                // 如果是SVG，直接保存
                if ($result['mime'] === 'image/svg+xml') {
                    @file_put_contents($cacheFile, $result['content']);
                } else {
                    // 对于其他格式，如果不是PNG，尝试转换
                    if ($result['mime'] !== 'image/png') {
                        $converted = $this->convertToPng($result['content'], $result['mime']);
                        if ($converted) {
                            $result['content'] = $converted;
                            $result['mime'] = 'image/png';
                            $cacheFile = $this->getCacheFile($url) . '.png';
                        }
                    }
                    @file_put_contents($cacheFile, $result['content']);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->options['debug']) {
                error_log('Error in fetch: ' . $e->getMessage());
            }
            // 确保在出错时也能返回默认图标
            return $this->getDefaultIcon($url);
        }
    }
    
    /**
     * 从URL获取favicon
     *
     * @param string $url
     * @return array{content: string, mime: string, cached: bool}
     * @throws \Exception
     */
    private function fetchFromUrl(string $url): array
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            // 如果无法解析host，使用URL作为备用
            if (empty($host)) {
                $host = $url;
            }
            
            // 1. 尝试从HTML中获取图标链接
            $html = $this->getHtmlContent($url);
            $iconUrl = $this->extractIconUrlFromHtml($html, $url);
            
            if ($iconUrl) {
                $icon = $this->downloadIcon($iconUrl);
                if ($icon) {
                    return $icon;
                }
            }
            
            // 2. 尝试常见的图标路径
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'http';
            
            // 2.1 尝试根目录下的图标
            foreach (self::SUPPORTED_FORMATS as $format) {
                $iconUrl = "{$scheme}://{$host}/favicon.{$format}";
                $icon = $this->downloadIcon($iconUrl);
                if ($icon) {
                    return $icon;
                }
            }
            
            // 2.2 尝试 /static/ 目录下的图标
            foreach (self::SUPPORTED_FORMATS as $format) {
                $iconUrl = "{$scheme}://{$host}/static/favicon.{$format}";
                $icon = $this->downloadIcon($iconUrl);
                if ($icon) {
                    return $icon;
                }
            }
            
            // 2.3 尝试 /assets/ 目录下的图标
            foreach (self::SUPPORTED_FORMATS as $format) {
                $iconUrl = "{$scheme}://{$host}/assets/favicon.{$format}";
                $icon = $this->downloadIcon($iconUrl);
                if ($icon) {
                    return $icon;
                }
            }
            
            // 3. 尝试Google的favicon服务
            $googleIconUrl = "https://www.google.com/s2/favicons?domain=" . $host;
            $icon = $this->downloadIcon($googleIconUrl);
            if ($icon) {
                return $icon;
            }
            
            // 4. 尝试 DuckDuckGo 的 favicon 服务
            $ddgIconUrl = "https://icons.duckduckgo.com/ip3/{$host}.ico";
            $icon = $this->downloadIcon($ddgIconUrl);
            if ($icon) {
                return $icon;
            }
            
            // 5. 返回默认图标
            return $this->getDefaultIcon($host);
            
        } catch (\Exception $e) {
            if ($this->options['debug']) {
                error_log('Error fetching favicon: ' . $e->getMessage());
            }
            // 确保在出错时也能返回默认图标
            return $this->getDefaultIcon($url);
        }
    }
    
    /**
     * 从HTML中提取图标URL
     *
     * @param string $html
     * @param string $baseUrl
     * @return string|null
     */
    private function extractIconUrlFromHtml(string $html, string $baseUrl): ?string
    {
        $patterns = [
            '/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:shortcut )?icon["\']/i',
            '/<link[^>]+rel=["\']apple-touch-icon["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']apple-touch-icon["\']/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $iconUrl = $matches[1];
                return $this->resolveUrl($iconUrl, $baseUrl);
            }
        }
        
        return null;
    }
    
    /**
     * 下载图标
     *
     * @param string $url
     * @return array{content: string, mime: string, cached: bool}|null
     */
    private function downloadIcon(string $url): ?array
    {
        $content = $this->fetchWithRetry($url);
        if ($content === false) {
            return null;
        }
        
        // 获取Content-Type
        $mime = null;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^Content-Type:\s*([^;]+)/i', $header, $matches)) {
                    $mime = trim($matches[1]);
                    break;
                }
            }
        }
        
        // 验证是否为图片
        if (!$this->isValidImage($content, $mime)) {
            return null;
        }
        
        return [
            'content' => $content,
            'mime' => $mime ?? $this->getMimeType($content),
            'cached' => false
        ];
    }
    
    /**
     * 获取HTML内容
     *
     * @param string $url
     * @return string
     * @throws \Exception
     */
    private function getHtmlContent(string $url): string
    {
        $content = $this->fetchWithRetry($url, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
        ]);
        
        if ($content === false) {
            throw new \Exception('Failed to fetch HTML content');
        }
        
        return $content;
    }
    
    /**
     * 获取缓存文件路径
     *
     * @param string $url
     * @return string
     */
    private function getCacheFile(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $hash = md5($host);
        // 保持原始格式，不强制转换为PNG
        return $this->cacheDir . '/' . $host . '_' . $hash;
    }
    
    /**
     * 规范化URL
     *
     * @param string $url
     * @return string
     */
    private function normalizeUrl(string $url): string
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . $url;
        }
        return rtrim($url, '/');
    }
    
    /**
     * 解析相对URL为绝对URL
     *
     * @param string $url
     * @param string $baseUrl
     * @return string
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (strpos($url, '//') === 0) {
            return 'http:' . $url;
        }
        
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        $baseParts = parse_url($baseUrl);
        $basePath = isset($baseParts['path']) ? dirname($baseParts['path']) : '';
        
        if (strpos($url, '/') === 0) {
            return $baseParts['scheme'] . '://' . $baseParts['host'] . $url;
        }
        
        return $baseParts['scheme'] . '://' . $baseParts['host'] . $basePath . '/' . $url;
    }
    
    /**
     * 验证是否为有效的图片
     *
     * @param string $content
     * @param string|null $mime
     * @return bool
     */
    private function isValidImage(string $content, ?string $mime): bool
    {
        if (empty($content)) {
            return false;
        }
        
        // 如果提供了MIME类型，先检查它
        if ($mime && strpos($mime, 'image/') !== 0) {
            return false;
        }
        
        // 使用finfo检查实际内容
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($content);
        
        return strpos($detectedMime, 'image/') === 0;
    }
    
    /**
     * 获取MIME类型
     *
     * @param string $content
     * @return string
     */
    private function getMimeType(string $content): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($content);
    }
    
    /**
     * 确保缓存目录存在
     */
    private function ensureCacheDirectoryExists(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * 将图片转换为PNG格式
     *
     * @param string $content 图片内容
     * @param string $mime 原始MIME类型
     * @return string|null 转换后的PNG内容，失败返回null
     */
    private function convertToPng(string $content, string $mime): ?string
    {
        try {
            // 对于SVG，使用Imagick处理
            if ($mime === 'image/svg+xml') {
                if (extension_loaded('imagick')) {
                    $imagick = new \Imagick();
                    $imagick->readImageBlob($content);
                    $imagick->setImageFormat('png');
                    $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                    return $imagick->getImageBlob();
                }
                // 如果没有Imagick，尝试使用GD
            }
            
            // 使用GD处理其他格式
            if (extension_loaded('gd')) {
                $image = @imagecreatefromstring($content);
                if ($image === false) {
                    return null;
                }
                
                // 创建透明背景
                $width = imagesx($image);
                $height = imagesy($image);
                $png = imagecreatetruecolor($width, $height);
                imagealphablending($png, false);
                imagesavealpha($png, true);
                $transparent = imagecolorallocatealpha($png, 255, 255, 255, 127);
                imagefilledrectangle($png, 0, 0, $width, $height, $transparent);
                
                // 复制原图到新图
                imagecopy($png, $image, 0, 0, 0, 0, $width, $height);
                
                // 输出为PNG
                ob_start();
                imagepng($png, null, 9);
                $result = ob_get_clean();
                
                // 清理资源
                imagedestroy($image);
                imagedestroy($png);
                
                return $result;
            }
            
            return null;
        } catch (\Exception $e) {
            if ($this->options['debug']) {
                error_log('Error converting image: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * 根据MIME类型获取文件扩展名
     *
     * @param string $mime
     * @return string
     */
    private function getExtensionFromMime(string $mime): string
    {
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico'
        ];
        
        return $map[$mime] ?? 'png';
    }
} 