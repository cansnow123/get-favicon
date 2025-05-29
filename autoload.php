<?php
/**
 * 简单的自动加载器
 */
spl_autoload_register(function ($class) {
    // 将命名空间转换为文件路径
    $prefix = 'Iowen\\GetFavicon\\';
    $base_dir = __DIR__ . '/src/';

    // 检查类是否使用我们的命名空间前缀
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 获取相对类名
    $relative_class = substr($class, $len);

    // 将命名空间分隔符替换为目录分隔符
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // 如果文件存在，则加载它
    if (file_exists($file)) {
        require $file;
    }
}); 