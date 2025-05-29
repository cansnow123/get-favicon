# GetFavicon

一个简单而强大的PHP网站图标获取工具，支持自动生成默认图标、代理访问、缓存管理等功能。

## 功能特点

- 🎯 智能获取网站图标（favicon）
- 🌐 支持多种图标格式（ICO、PNG、JPG、SVG、GIF）
- 🔄 自动重试和错误处理
- 🎨 动态生成默认SVG图标
- 🌍 智能代理支持（区分国内外网站）
- 💾 本地缓存管理
- 🔍 多种图标获取策略
- 🛡️ 完善的错误处理机制

## 安装要求

- PHP >= 7.4
- 扩展：
  - GD 或 Imagick（用于图片处理）
  - fileinfo（用于MIME类型检测）

## 安装

```bash
composer require iowen/get-favicon
```

## 基本使用

```php
use Iowen\GetFavicon\FaviconFetcher;

// 创建实例
$fetcher = new FaviconFetcher();

// 获取图标
$result = $fetcher->fetch('https://example.com');

// 输出图标
header('Content-Type: ' . $result['mime']);
echo $result['content'];
```

## 高级配置

### 自定义缓存目录和选项

```php
$fetcher = new FaviconFetcher(
    cacheDir: 'path/to/cache',    // 缓存目录
    defaultIcon: 'default.png',   // 默认图标
    cacheTtl: 86400,             // 缓存时间（秒）
    options: [
        'timeout' => 10,         // 超时时间
        'debug' => true,         // 调试模式
        'max_retries' => 3,      // 最大重试次数
        'retry_delay' => 2       // 重试延迟（秒）
    ]
);
```

### 代理配置

在 `config/proxies.php` 中配置代理服务器：

```php
return [
    'servers' => [
        'cn' => [
            [
                'name' => 'proxy1',
                'http' => 'http://proxy1.example.com:8080',
                'https' => 'http://proxy1.example.com:8080',
                'weight' => 1
            ]
        ],
        'global' => [
            [
                'name' => 'proxy2',
                'http' => 'http://proxy2.example.com:8080',
                'https' => 'http://proxy2.example.com:8080',
                'weight' => 1
            ]
        ]
    ],
    'strategy' => [
        'use_proxy_for_cn' => true,      // 是否对中国大陆网站使用代理
        'use_proxy_for_global' => false  // 是否对境外网站使用代理
    ],
    'health_check' => [
        'timeout' => 5,
        'test_url' => [
            'cn' => 'http://www.baidu.com',
            'global' => 'http://www.google.com'
        ]
    ]
];
```

### 域名配置

在 `config/domains.php` 中配置域名规则：

```php
return [
    'cn_whitelist' => [      // 中国大陆域名白名单
        'baidu.com',
        'qq.com'
    ],
    'global_whitelist' => [  // 境外域名白名单
        'google.com',
        'github.com'
    ],
    'cn_suffixes' => [       // 中国大陆域名后缀
        '.cn',
        '.com.cn',
        '.net.cn'
    ]
];
```

## 自定义SVG图标生成

可以通过修改 `SvgGenerator` 类来自定义默认图标的生成：

- 修改颜色方案
- 调整图案样式
- 添加新的图案类型

## 注意事项

1. 确保缓存目录具有写入权限
2. 建议在生产环境中启用调试模式以便排查问题
3. 代理服务器需要支持HTTP/HTTPS协议
4. 图片处理需要GD或Imagick扩展

## 常见问题

### Q: 为什么某些网站的图标无法获取？
A: 可能的原因：
- 网站没有提供图标
- 网站使用了特殊的图标路径
- 网站限制了图标访问
- 网络连接问题

### Q: 如何强制刷新缓存？
A: 使用 `fetch` 方法的第二个参数：
```php
$result = $fetcher->fetch('https://example.com', true);
```

### Q: 如何自定义默认图标？
A: 有两种方式：
1. 提供默认图标文件
2. 修改 `SvgGenerator` 类的配置

## 贡献

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License
