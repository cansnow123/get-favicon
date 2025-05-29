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
        $this->options = array_merge([
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'debug' => false,
            'max_retries' => 2,  // 最大重试次数
            'retry_delay' => 1   // 重试延迟（秒）
        ], $options);
        
        // 设置默认代理配置
        $this->proxies = array_merge([
            'cn' => [  // 中国大陆代理
                'http' => null,
                'https' => null
            ],
            'global' => [  // 境外代理
                'http' => null,
                'https' => null
            ]
        ], $proxies);
        
        $this->ensureCacheDirectoryExists();
    }
    
    /**
     * 判断域名是否为中国大陆域名
     *
     * @param string $host
     * @return bool
     */
    private function isChineseDomain(string $host): bool
    {
        // 常见中国大陆域名后缀
        $cnSuffixes = [
            '.cn', '.com.cn', '.net.cn', '.org.cn', '.gov.cn', '.edu.cn',
            '.ac.cn', '.mil.cn', '.biz.cn', '.info.cn', '.name.cn',
            '.moe.cn', '.xn--fiqs8s', // 中文域名
        ];
        
        foreach ($cnSuffixes as $suffix) {
            if (strcasecmp(substr($host, -strlen($suffix)), $suffix) === 0) {
                return true;
            }
        }
        
        // 检查IP是否为中国大陆IP（这里可以接入IP数据库）
        // TODO: 接入IP数据库进行更准确的判断
        
        return false;
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
        if ($this->isChineseDomain($host)) {
            return $this->proxies['cn'];
        }
        return $this->proxies['global'];
    }

    /**
     * 创建HTTP上下文
     *
     * @param string $url
     * @param array $headers
     * @return resource
     */
    private function createContext(string $url, array $headers = []): resource
    {
        $proxyConfig = $this->getProxyConfig($url);
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
        
        while ($retries <= $this->options['max_retries']) {
            try {
                $context = $this->createContext($url, $headers);
                $content = @file_get_contents($url, false, $context);
                
                if ($content !== false) {
                    return $content;
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
     * 获取网站的favicon
     *
     * @param string $url 网站URL
     * @param bool $refresh 是否强制刷新缓存
     * @return array{content: string, mime: string, cached: bool}
     * @throws \Exception
     */
    public function fetch(string $url, bool $refresh = false): array
    {
        $url = $this->normalizeUrl($url);
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
            foreach (self::SUPPORTED_FORMATS as $format) {
                $iconUrl = $this->buildIconUrl($url, $format);
                $icon = $this->downloadIcon($iconUrl);
                if ($icon) {
                    return $icon;
                }
            }
            
            // 3. 尝试Google的favicon服务
            $googleIconUrl = "https://www.google.com/s2/favicons?domain=" . parse_url($url, PHP_URL_HOST);
            $icon = $this->downloadIcon($googleIconUrl);
            if ($icon) {
                return $icon;
            }
            
            // 4. 返回默认图标
            return $this->getDefaultIcon();
            
        } catch (\Exception $e) {
            if ($this->options['debug']) {
                error_log('Error fetching favicon: ' . $e->getMessage());
            }
            return $this->getDefaultIcon();
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
     * 构建常见的图标URL
     *
     * @param string $url
     * @param string $format
     * @return string
     */
    private function buildIconUrl(string $url, string $format): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return "https://{$host}/favicon.{$format}";
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
     * 获取默认图标
     *
     * @return array{content: string, mime: string, cached: bool}
     * @throws \Exception
     */
    private function getDefaultIcon(): array
    {
        if (!file_exists($this->defaultIcon)) {
            throw new \Exception('Default icon file not found');
        }
        
        $content = file_get_contents($this->defaultIcon);
        $mime = $this->getMimeType($content);
        
        return [
            'content' => $content,
            'mime' => $mime,
            'cached' => false
        ];
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