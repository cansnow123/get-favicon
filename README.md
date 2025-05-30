# 🎯 GetFavicon

一个优雅而强大的PHP网站图标获取工具，支持智能代理、自动生成默认图标、缓存管理等特性。

## ✨ 特性

- 🔍 智能获取网站图标（favicon）
  - 支持多种图标格式（ICO、PNG、JPG、SVG、GIF）
  - 多级获取策略，确保最大程度获取成功
  - 智能错误处理和自动重试机制

- 🎨 默认图标生成
  - 基于域名自动生成独特的SVG图标
  - 支持多种艺术风格（beam、pixel、sunset）
  - 固定域名生成固定样式，确保一致性

- 🌍 智能代理支持
  - 自动区分国内外网站
  - 支持分组配置多个代理服务器
  - 内置代理健康检查和故障转移
  - 可配置的代理使用策略

- 💾 高效缓存系统
  - 本地文件缓存
  - 可配置缓存有效期
  - 自动缓存清理

## 🚀 安装要求

- PHP >= 7.4
- 必需扩展：
  - GD 或 Imagick（用于图片处理）
  - fileinfo（用于MIME类型检测）

## 📦 安装

1. 下载项目文件
2. 将项目文件上传到网站目录
3. 设置网站运行目录为 `public`

## 🎮 基本使用

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

## ⚙️ 高级配置

### 自定义选项

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
        'cn' => [  // 中国大陆代理服务器
            [
                'name' => 'proxy1',
                'http' => 'http://proxy1.example.com:8080',
                'https' => 'http://proxy1.example.com:8080',
                'weight' => 1
            ]
        ],
        'global' => [  // 国际代理服务器
            [
                'name' => 'proxy2',
                'http' => 'http://proxy2.example.com:8080',
                'https' => 'http://proxy2.example.com:8080',
                'weight' => 1
            ]
        ]
    ],
    'strategy' => [
        'use_proxy_for_cn' => true,     // 是否对中国大陆网站使用代理
        'use_proxy_for_global' => false  // 是否对境外网站使用代理
    ],
    'health_check' => [  // 健康检查配置
        'timeout' => 5,
        'test_url' => [
            'cn' => 'http://www.baidu.com',
            'global' => 'http://www.google.com'
        ]
    ]
];
```

## 📝 域名配置

在 `config/domains.php` 中配置域名规则：

```php
return [
    'cn_whitelist' => [  // 中国大陆域名白名单
        'baidu.com',
        'qq.com'
    ],
    'global_whitelist' => [  // 国际域名白名单
        'google.com',
        'github.com'
    ],
    'cn_suffixes' => [  // 中国大陆域名后缀
        '.cn',
        '.com.cn'
    ]
];
```

## 🤝 贡献

欢迎提交问题和改进建议！

## 📄 开源协议

本项目采用 MIT 协议开源，详见 [LICENSE](LICENSE) 文件。
