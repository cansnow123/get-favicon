# getFavicon

一个轻量级的PHP库，用于获取网站的Favicon图标。支持多种图标格式（.ico, .png, .jpg, .svg, .gif），无需任何外部依赖。

## 特性

- 轻量级设计，无外部依赖
- 支持多种图标格式（.ico, .png, .jpg, .svg, .gif）
- 智能图标获取策略：
  1. 从HTML中解析图标链接
  2. 尝试常见的图标路径
  3. 使用Google的favicon服务作为备选
  4. 返回默认图标作为最后选项
- 简单的文件缓存机制
- 支持强制刷新缓存
- 支持CDN缓存

## 安装

1. 下载代码到您的网站目录
2. 确保 `cache` 目录可写（755权限）
3. 将默认图标放在 `public/favicon.png`

## 使用方法

### 基本使用

```php
require_once 'autoload.php';

use Iowen\GetFavicon\FaviconFetcher;

// 创建favicon获取器实例
$fetcher = new FaviconFetcher(
    'path/to/cache',  // 缓存目录
    'path/to/default.png',  // 默认图标
    2592000,  // 缓存时间（秒）
    [
        'debug' => true,  // 开启调试模式
        'timeout' => 5,  // 超时时间
        'user_agent' => '...'  // 自定义User-Agent
    ]
);

// 获取favicon
$result = $fetcher->fetch('https://example.com');

// 输出图标
header('Content-Type: ' . $result['mime']);
echo $result['content'];
```

### Web服务器配置

项目部署在网站根目录下，入口文件为 `public/index.php`。

#### Nginx

```nginx
# 网站根目录配置
location / {
    try_files $uri $uri/ /public/index.php?$query_string;
}

# 支持CDN缓存的规则 - 支持复杂URL
location ~ ^/([^/]+)(?:/.*)?\.png$ {
    try_files $uri $uri/ /public/index.php?url=$1;
}

# 或者使用更宽松的规则（如果上面的规则不够用）
# location ~ ^/([^/]+)(?:/.*)?\.png$ {
#     rewrite ^/([^/]+)(?:/.*)?\.png$ /public/index.php?url=$1 last;
# }
```

#### Apache

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # 网站根目录配置
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ public/index.php?url=$1 [L,QSA]
    
    # 支持CDN缓存的规则 - 支持复杂URL
    RewriteRule ^([^/]+)(?:/.*)?\.png$ public/index.php?url=$1 [L,QSA]
</IfModule>
```

### API调用

```
# 基本调用
GET /?url=example.com
GET /?url=https://example.com/path/to/page

# 强制刷新缓存
GET /?url=example.com&refresh=true

# CDN友好URL（支持复杂路径）
GET /example.com.png
GET /example.com/path/to/page.png
```

### 调试模式

在生产环境中建议开启调试模式，以便及时发现问题：

```php
$fetcher = new FaviconFetcher(
    __DIR__ . '/../cache',
    __DIR__ . '/../public/favicon.png',
    2592000, // 30天缓存
    [
        'debug' => true, // 生产环境建议开启
        'timeout' => 5
    ]
);
```

## 配置说明

- `cache_dir`: 缓存目录路径
- `default_icon`: 默认图标路径
- `cache_ttl`: 缓存有效期（默认30天）
- `options`: 其他选项
  - `debug`: 是否开启调试模式
  - `timeout`: 请求超时时间
  - `user_agent`: 自定义User-Agent

## 目录结构

```
getFavicon/
├── autoload.php          # 自动加载器
├── public/              # 公共文件目录
│   ├── index.php       # 入口文件
│   └── favicon.png     # 默认图标
├── src/                # 源代码目录
│   └── FaviconFetcher.php
└── cache/              # 缓存目录
```

## 许可证

MIT License

## 致谢

感谢 [jerrybendy/get_favicon](https://github.com/jerrybendy/get_favicon) 项目提供的灵感。
