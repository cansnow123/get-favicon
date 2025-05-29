<?php

namespace Iowen\GetFavicon;

class SvgGenerator
{
    private const CONFIG = [
        'width' => 100,
        'height' => 100,
        'variants' => [
            'beam' => [
                'colors' => [
                    ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90'],
                    ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#405059'],
                    ['#EA526F', '#E76B74', '#D7AF70', '#937B63', '#385B59'],
                    ['#2A2D34', '#009DDC', '#F26430', '#6761A8', '#009B72'],
                    ['#F7C1BB', '#885A5A', '#353A47', '#84B082', '#DC136C']
                ]
            ],
            'pixel' => [
                'colors' => [
                    ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#405059'],
                    ['#EA526F', '#E76B74', '#D7AF70', '#937B63', '#385B59'],
                    ['#2A2D34', '#009DDC', '#F26430', '#6761A8', '#009B72'],
                    ['#F7C1BB', '#885A5A', '#353A47', '#84B082', '#DC136C'],
                    ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90']
                ]
            ],
            'sunset' => [
                'colors' => [
                    ['#EA526F', '#E76B74', '#D7AF70', '#937B63', '#385B59'],
                    ['#2A2D34', '#009DDC', '#F26430', '#6761A8', '#009B72'],
                    ['#F7C1BB', '#885A5A', '#353A47', '#84B082', '#DC136C'],
                    ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90'],
                    ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#405059']
                ]
            ]
        ]
    ];

    /**
     * 生成SVG图标
     *
     * @param string $host 域名
     * @return string SVG内容
     */
    public function generate(string $host): string
    {
        // 生成随机图案
        $pattern = $this->generateRandomPattern($host);
        
        // 构建SVG
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="%d" height="%d">
                %s
            </svg>',
            self::CONFIG['width'],
            self::CONFIG['height'],
            $pattern
        );
    }

    /**
     * 生成随机图案
     *
     * @param string $host 域名
     * @return string SVG图案
     */
    private function generateRandomPattern(string $host): string
    {
        // 使用host生成固定的随机种子
        $seed = crc32($host);
        srand($seed);
        
        // 随机选择一种风格
        $variant = array_rand(self::CONFIG['variants']);
        $variantConfig = self::CONFIG['variants'][$variant];
        
        // 随机选择一组颜色
        $colors = $variantConfig['colors'][array_rand($variantConfig['colors'])];
        
        switch ($variant) {
            case 'beam':
                return $this->generateBeamPattern($colors);
            case 'pixel':
                return $this->generatePixelPattern($colors);
            case 'sunset':
                return $this->generateSunsetPattern($colors);
            default:
                return $this->generateBeamPattern($colors);
        }
    }

    /**
     * 生成光束风格图案
     *
     * @param array $colors 颜色数组
     * @return string SVG图案
     */
    private function generateBeamPattern(array $colors): string
    {
        $svg = '';
        $numBeams = 3;
        $width = self::CONFIG['width'];
        $height = self::CONFIG['height'];
        
        // 生成背景
        $svg .= sprintf('<rect fill="%s" x="0" y="0" width="%d" height="%d"/>', $colors[0], $width, $height);
        
        // 生成光束
        for ($i = 0; $i < $numBeams; $i++) {
            $x1 = rand(0, $width);
            $y1 = rand(0, $height);
            $x2 = rand(0, $width);
            $y2 = rand(0, $height);
            $color = $colors[rand(1, count($colors) - 1)];
            $opacity = rand(30, 70) / 100;
            
            $svg .= sprintf(
                '<path d="M%d,%d Q%d,%d %d,%d" stroke="%s" stroke-width="%d" fill="none" opacity="%s"/>',
                $x1, $y1,
                rand(0, $width), rand(0, $height),
                $x2, $y2,
                $color,
                rand(10, 30),
                $opacity
            );
        }
        
        return $svg;
    }

    /**
     * 生成像素风格图案
     *
     * @param array $colors 颜色数组
     * @return string SVG图案
     */
    private function generatePixelPattern(array $colors): string
    {
        $svg = '';
        $width = self::CONFIG['width'];
        $height = self::CONFIG['height'];
        $pixelSize = 10;
        
        // 生成背景
        $svg .= sprintf('<rect fill="%s" x="0" y="0" width="%d" height="%d"/>', $colors[0], $width, $height);
        
        // 生成像素
        for ($x = 0; $x < $width; $x += $pixelSize) {
            for ($y = 0; $y < $height; $y += $pixelSize) {
                if (rand(0, 100) < 30) { // 30%的概率生成像素
                    $color = $colors[rand(1, count($colors) - 1)];
                    $opacity = rand(40, 90) / 100;
                    $svg .= sprintf(
                        '<rect fill="%s" x="%d" y="%d" width="%d" height="%d" opacity="%s"/>',
                        $color,
                        $x,
                        $y,
                        $pixelSize,
                        $pixelSize,
                        $opacity
                    );
                }
            }
        }
        
        return $svg;
    }

    /**
     * 生成日落风格图案
     *
     * @param array $colors 颜色数组
     * @return string SVG图案
     */
    private function generateSunsetPattern(array $colors): string
    {
        $svg = '';
        $width = self::CONFIG['width'];
        $height = self::CONFIG['height'];
        
        // 生成渐变背景
        $gradientId = 'gradient_' . uniqid();
        $svg .= sprintf(
            '<defs>
                <linearGradient id="%s" x1="0%%" y1="0%%" x2="0%%" y2="100%%">
                    <stop offset="0%%" style="stop-color:%s"/>
                    <stop offset="100%%" style="stop-color:%s"/>
                </linearGradient>
            </defs>',
            $gradientId,
            $colors[0],
            $colors[1]
        );
        
        // 生成背景
        $svg .= sprintf(
            '<rect fill="url(#%s)" x="0" y="0" width="%d" height="%d"/>',
            $gradientId,
            $width,
            $height
        );
        
        // 生成装饰元素
        for ($i = 0; $i < 3; $i++) {
            $color = $colors[rand(2, count($colors) - 1)];
            $opacity = rand(20, 40) / 100;
            $size = rand(20, 40);
            $x = rand(0, $width - $size);
            $y = rand(0, $height - $size);
            
            $svg .= sprintf(
                '<circle fill="%s" cx="%d" cy="%d" r="%d" opacity="%s"/>',
                $color,
                $x + $size/2,
                $y + $size/2,
                $size/2,
                $opacity
            );
        }
        
        return $svg;
    }
} 