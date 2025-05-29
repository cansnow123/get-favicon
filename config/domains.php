<?php

return [
    // 中国大陆域名白名单
    'cn_whitelist' => [
        // 示例域名，实际使用时请清空或替换
        // 'baidu.com',
        // 'qq.com',
        // 'taobao.com',
        // 'jd.com',
        // '163.com',
        // '126.com',
        // 'sina.com.cn',
        // 'sohu.com',
        // 'weibo.com',
        // 'zhihu.com',
        // 'bilibili.com',
        // 'douyin.com',
        // 'kuaishou.com',
        // 'xiaohongshu.com',
        // 'meituan.com',
        // 'dianping.com',
        // 'ctrip.com',
        // '12306.cn',
        // 'aliyun.com',
        // 'tencent.com',
        // 'alipay.com',
        // 'weixin.qq.com',
        // 'qq.com',
        // 'jd.com',
        // 'tmall.com',
        // '1688.com',
        // 'zmt.wiki'
    ],
    
    // 境外域名白名单（直接访问）
    'global_whitelist' => [
        // 示例域名，实际使用时请清空或替换
        // 'google.com',
        // 'facebook.com',
        // 'twitter.com',
        // 'youtube.com',
        // 'github.com',
        // 'stackoverflow.com',
        // 'medium.com',
        // 'linkedin.com',
        // 'instagram.com',
        // 'reddit.com',
        // 'wikipedia.org',
        // 'amazon.com',
        // 'microsoft.com',
        // 'apple.com',
        // 'netflix.com',
        // 'spotify.com',
        // 'discord.com',
        // 'slack.com',
        // 'zoom.us',
        // 'dropbox.com'
    ],
    
    // 中国大陆域名后缀列表
    'cn_suffixes' => [
        '.cn', '.com.cn', '.net.cn', '.org.cn', '.gov.cn', '.edu.cn',
        '.ac.cn', '.mil.cn', '.biz.cn', '.info.cn', '.name.cn',
        '.moe.cn', '.xn--fiqs8s', // 中文域名
        '.wang', '.top', '.xyz', '.site', '.online', '.tech', '.store',
        '.shop', '.club', '.vip', '.work', '.ltd', '.group', '.ink',
        '.design', '.website', '.space', '.press', '.host', '.fun'
    ]
]; 