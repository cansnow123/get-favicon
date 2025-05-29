<?php

return [
    // 代理策略配置
    'strategy' => [
        'use_proxy_for_cn' => false,     // 是否对中国大陆域名使用代理
        'use_proxy_for_global' => false,  // 是否对境外域名使用代理
    ],
    
    // 代理服务器配置
    'servers' => [
        'cn' => [  // 中国大陆代理列表
            // 示例配置，实际使用时请清空或替换
            // [
            //     'name' => 'cn-proxy-1',
            //     'http' => 'http://your-proxy-server:3128',
            //     'https' => 'https://your-proxy-server:3128',
            //     'weight' => 1
            // ]
        ],
        'global' => [  // 境外代理列表
            // 示例配置，实际使用时请清空或替换
            // [
            //     'name' => 'global-proxy-1',
            //     'http' => 'http://your-proxy-server:3128',
            //     'https' => 'https://your-proxy-server:3128',
            //     'weight' => 1
            // ]
        ]
    ],
    
    // 代理服务器健康检查配置
    'health_check' => [
        'interval' => 300,        // 检查间隔（秒）
        'fail_threshold' => 3,    // 失败阈值
        'recovery_time' => 600,   // 恢复时间（秒）
        'timeout' => 5,           // 超时时间（秒）
        'test_url' => [           // 测试URL
            'cn' => 'http://www.baidu.com',
            'global' => 'https://www.google.com'
        ]
    ]
]; 