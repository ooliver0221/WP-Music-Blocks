# WP Music Blocks

一个 WordPress 区块插件，在文章中插入美观的音乐卡片。粘贴 Apple Music、QQ音乐 或 网易云音乐 的链接，自动生成带有专辑封面、渐变背景的展示卡片。

## 支持平台

- **Apple Music** — 歌曲 / 专辑链接
- **QQ音乐** — 歌曲链接（自动获取专辑曲目）
- **网易云音乐** — 歌曲 / 专辑链接

## 功能特点

- 粘贴链接即可自动获取歌曲名、歌手、专辑、封面、曲目列表等信息
- 自动提取专辑封面的主色调，生成 Apple Music 风格的流体渐变背景
- 支持**歌曲卡片**（封面 + 信息横向排列）和**专辑卡片**（曲目列表在下方）
- 点击卡片跳转到原始音乐平台收听
- 自适应布局，移动端友好
- 响应主题字体

## 安装

1. 将 `wp-music-blocks` 文件夹上传到 `/wp-content/plugins/` 目录
2. 在 WordPress 后台「插件」页面启用
3. 在区块编辑器中搜索「音乐卡片」并插入
4. 粘贴音乐链接，卡片会自动生成

### 环境要求

- WordPress 6.0 或更高版本
- PHP 7.4 或更高版本
- PHP GD 扩展（用于提取封面颜色）

## 使用方式

1. 编辑文章或页面，点击「+」添加区块
2. 搜索「音乐卡片」
3. 粘贴音乐链接（例如 `https://music.apple.com/cn/album/123456`）
4. 插件自动识别平台并获取歌曲/专辑信息
5. 在右侧面板中选择卡片类型：**歌曲** 或 **专辑**
6. 如需重新获取数据，点击「刷新数据」

## 卡片类型

| 类型 | 布局 | 说明 |
|---|---|---|
| 歌曲 | 封面居左，信息居右 | 展示一首歌的基本信息 |
| 专辑 | 封面与信息在上方，曲目列表在下方 | 展示完整专辑曲目 |

## 常见问题

**链接无法识别怎么办？**

插件会依次尝试匹配 Apple Music → 网易云 → QQ音乐的解析规则。如果自动识别失败，可以在右侧面板中手动选择平台后重试。

**Apple Music 个人资料库链接能用吗？**

不能。`music.apple.com/cn/library/...` 这类链接需要登录才能访问，服务器无法获取。请使用公开的歌曲/专辑链接。

**卡片颜色是怎么来的？**

插件下载专辑封面图片，通过 PHP GD 库分析像素颜色分布，提取出现频率最高的颜色作为卡片背景主色，并生成多个柔和的渐变光斑叠加在背景上。

**支持经典编辑器吗？**

本插件是 Gutenberg 区块，仅在区块编辑器中可用。

## 设置

在「设置 → Music Blocks」中可以调整：

- **界面语言** — 编辑器内显示语言（中文 / English）
- **卡片字体** — 自定义 CSS `font-family`，默认跟随主题

## 许可证

MIT License

Copyright (c) 2025 ooliver

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
