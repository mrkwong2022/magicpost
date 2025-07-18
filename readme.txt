﻿=== MagicPost - WordPress文章管理功能增强插件 ===
Contributors: wbolt,mrkwong
Donate link: https://www.wbolt.com/
Tags: post migration, autopost, html cleaner, social widget, TOC
Requires at least: 5.6
Tested up to: 6.8
Stable tag: 1.3.1
License: GNU General Public License v2.0 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MagicPost（中文为魔法文章），如其名，该插件的主要目的是为WordPress的文章管理赋予更多高效，增强的功能。如定时发布管理，文章搬家，文章翻译，HTML代码清洗，下载文件管理，社交分享小组件和TOC内容目录。

== Description ==

MagicPost（中文为魔法文章），如其名，该插件的主要目的是为WordPress的文章管理赋予更多高效，增强的功能。如定时发布管理，文章搬家，文章翻译，HTML代码清洗，社交分享小组件和TOC内容目录。

> <strong>MagicPost Pro</strong>
>
> 这是MagicPost的免费版本，包括HTML代码清理、下载管理、社交分享等大部分功能。如需使用到定时发布、文章搬家等功能，则需要升级到Pro版本！ <a href="https://www.wbolt.com/plugins/magicpost?utm_source=wp&utm_medium=link&utm_campaign=magicpost" rel="friend" title="MagicPost">点击了解及购买MagicPost Pro版本!</a>

### 1.定时发布

WordPress本身提供定时发布支持，但原生的文章定时发布功能过于单一，无法满足站长更加个性化的发布需求。插件增强版定时发布，支持：
* **定时设置**-支持将不同文章类型的草稿或者待审内容添加至定时发布清单，按所设定的定时规则自动发布；
* **定时列表**-支持列表查看定时发布文章内容。

> ℹ️ <strong>Tips</strong> 
> 
> 1.定时发布任务失败可能是由于WordPress定时任务不生效导致，查看<a href="https://www.wbolt.com/wordpress-cron-job.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" rel="friend" title="WordPress定时任务创建和修改">WordPress定时任务创建和修改</a>以进一步排查问题。
> 2.不建议单日定时发布文章超过100篇，一方面是网站的百度普通收录推送配额每天只有100条；另一方面是过多定时任务可能会影响网站性能。
> 3.定时发布文章时间区间应与网站访客分布时间相近，建议选择9:00-22:00之间。
> 4.如果您的网站使用了Cloudflare，也可能导致定时任务失效，查看<a href="https://www.wbolt.com/fix-wp-cron-not-working-issue-with-cloudflare.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" rel="friend" title="如何解决Cloudflare导致定时任务失败？">具体的解决办法</a>。

### 2.文章搬家
WordPress原生支持修改文章分类或者标签，但如果希望批量修改文章分类或者标签，原生功能就显得鸡肋了。为此我们专门定制了更高效快捷的文章分类或者标签管理功能。
* **按分类**-支持按文章分类、关键词筛选文章，批量移除分类、新增分类及重置分类；
* **按标签**-支持按关键词、标签ID筛选文章，批量重置或者增加文章标签。

> ℹ️ <strong>Tips</strong> 
> 
> 1.对文章的分类和标签执行批量处理是不可逆操作，建议在执行文章搬家相关操作前进行<a href="https://www.wbolt.com/14-best-wordpress-database-plugins.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" title="数据库备份">数据库备份</a>。
> 2.宝塔面板用户数据库备份操作无需依赖插件，直接通过宝塔控制面板即可<a href="https://www.wbolt.com/bt-panel-database-management.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" title="完成数据库备份操作">完成数据库备份操作</a>。
> 3.如文章搬家过程中，需要删除或者修改分类和标签，应该做好<a href="https://www.wbolt.com/301-redirects-wordpress.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" title="301重定向">301重定向</a>工作，也可以安装<a href="https://www.wbolt.com/plugins/bsl-pro?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank" title="SEO插件">Smart SEO Tool</a>插件实现。

### 3.文章翻译

文章翻译的插件功能的主要目的在于方便站长通过英译中，或者中译英的方式，为网站生成大量的“原创”内容。通过翻译的方式取得内容，比单纯的复制粘贴，原封不动地采集的内容，会优质得多。
* **翻译API接口**-支持谷歌云翻译官方API接口和免费API接口，及百度翻译API接口 ；
* **自动或者手动翻译**-插件支持设置自动翻译或者手动翻译，其中自动翻译会对草稿内容进行检查，定时执行翻译任务；手动翻译则需要在草稿列表执行单篇翻译或者多篇批量翻译。
* **翻译语言**-目前仅提供中译英或者英译中，后续再考虑是否加入更多的语种。
* **错误日志**-记录最近10条翻译错误，以便于站长更好地排查问题。

> ℹ️ <strong>Tips</strong> 
> 
> 1.谷歌翻译API（官方）和百度翻译API均属于付费服务，需要购买后才可以使用。
> 2.谷歌翻译API（第三方）属于免费服务，一般情况下，境外服务器可以直接调用；境内服务器可以选择闪电博代理服务。
> 3.谷歌翻译API（第三方）使用闪电博代理服务时，可能会存在不稳定的情况，如无法使用，请稍后再试。
> 4.百度翻译API属于<a href="https://ai.baidu.com/tech/mt/doc_trans" target="_blank">文档翻译</a>，属于非即时数据返回。执行翻译后，需等待几分钟返回结果，请勿反复操作。

### 4.HTML代码清理
即原来的HTML代码优化工具插件（Clear HTML Tags），非常实用的WordPress文章编辑辅助功能，可以帮助站长快速实现删除HTML代码不需要的常见HTML标签及标签属性，常用的代码格式优化。

> ℹ️ <strong>Tips</strong> 
> 
> 1.如需使用到正则表达式执行搜索替换，可以<a href="https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Guide/Regular_expressions" target="_blank">深入学习正则表达式</a>。
> 2.无论是标签、标签属性或者标签及内容删除，建议基于主题样式定义进行规则配置，以做到事半功倍的效果。

### 5.下载管理
原WP资源下载管理插件，支持站长发布文章时为访客提供本地下载、百度网盘及城通网盘等多种下载方式下载文章资源，并且支持设置登录会员或者评论回复后下载权限。

### 6.社交分享小组件
原博客社交分享组件插件的功能，整合了网站打赏，文章点赞、微海报和社交分享功能。

### 7.内容目录
支持基于文章内容的Heading标题快速生成位于文章正文或者侧栏小工具TOC目录。

== 其他WP插件 ==

MagicPost是一款专门为WordPress开发的<a href="https://www.wbolt.com/plugins/magicpost?utm_source=wp&utm_medium=link&utm_campaign=magicpost" rel="friend" title="MagicPost">文章管理增强插件</a>. 插件为站长提供增强版定时发布、文章搬家、文章翻译、HTML代码清理、下载管理和社交分享小组件等。

闪电博（<a href='https://www.wbolt.com/?utm_source=wp&utm_medium=link&utm_campaign=magicpost' rel='friend' title='闪电博官网'>wbolt.com</a>）专注于原创<a href='https://www.wbolt.com/themes' rel='friend' title='WordPress主题'>WordPress主题</a>和<a href='https://www.wbolt.com/plugins' rel='friend' title='WordPress插件'>WordPress插件</a>开发，为中文博客提供更多优质和符合国内需求的主题和插件。此外我们也会分享WordPress相关技巧和教程。

除了MagicPost插件外，目前我们还开发了以下WordPress插件：

- [多合一搜索自动推送管理插件-历史下载安装数200,000+](https://wordpress.org/plugins/baidu-submit-link/)
- [Spider Analyser–搜索引擎蜘蛛分析插件](https://wordpress.org/plugins/spider-analyser/)
- [热门关键词推荐插件-最佳关键词布局插件](https://wordpress.org/plugins/smart-keywords-tool/)
- [IMGspider-轻量外链图片采集插件](https://wordpress.org/plugins/imgspider/)
- [Smart SEO Tool-高效便捷的WP搜索引擎优化插件](https://wordpress.org/plugins/smart-seo-tool/)
- [WPTurbo -WordPress性能优化插件](https://wordpress.org/plugins/wpturbo/)
- [WP VK-WordPress知识付费插件](https://wordpress.org/plugins/wp-vk/)
- [Online Contact Widget-多合一在线客服插件](https://wordpress.org/plugins/online-contact-widget/)

- 更多主题和插件，请访问<a href='https://www.wbolt.com/?utm_source=wp&utm_medium=link&utm_campaign=magicpost' rel='friend' title='闪电博官网'>wbolt.com</a>!

如果你在WordPress主题和插件上有更多的需求，也希望您可以向我们提出意见建议，我们将会记录下来并根据实际情况，推出更多符合大家需求的主题和插件。

== WordPress资源 ==

由于我们是WordPress重度爱好者，在WordPress主题插件开发之余，我们还独立开发了一系列的在线工具及分享大量的WordPress教程，供国内的WordPress粉丝和站长使用和学习，其中包括：

**1. <a href="https://www.wbolt.com/learn?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank">Wordpress学院</a>:** 这里将整合全面的WordPress知识和教程，帮助您深入了解WordPress的方方面面，包括基础、开发、优化、电商及SEO等。WordPress大师之路，从这里开始。

**2. <a href="https://www.wbolt.com/tools/keyword-finder?utm_source=wp&utm_medium=link&utm_campaign=magicpost" target="_blank">关键词查找工具</a>:** 选择符合搜索用户需求的关键词进行内容编辑，更有机会获得更好的搜索引擎排名及自然流量。使用我们的关键词查找工具，以获取主流搜索引擎推荐关键词。

**3. <a href="https://www.wbolt.com/tools/wp-fixer?utm_source=wp&utm_medium=link&utm_campaign=magicpost">WordPress错误查找</a>:** 我们搜集了大部分WordPress最为常见的错误及对应的解决方案。您只需要在下方输入所遭遇的错误关键词或错误码，即可找到对应的处理办法。

**4. <a href="https://www.wbolt.com/tools/seo-toolbox?utm_source=wp&utm_medium=link&utm_campaign=magicpost">SEO工具箱</a>:** 收集整理国内外诸如链接建设、关键词研究、内容优化等不同类型的SEO工具。善用工具，往往可以达到事半功倍的效果。

**5. <a href="https://www.wbolt.com/tools/seo-topic?utm_source=wp&utm_medium=link&utm_campaign=magicpost">SEO优化中心</a>:** 无论您是 SEO 初学者，还是想学习高级SEO 策略，这都是您的 SEO 知识中心。

**6. <a href="https://www.wbolt.com/tools/spider-tool?utm_source=wp&utm_medium=link&utm_campaign=magicpost">蜘蛛查询工具</a>:** 网站每日都可能会有大量的蜘蛛爬虫访问，或者搜索引擎爬虫，或者安全扫描，或者SEO检测……满目琳琅。借助我们的蜘蛛爬虫检测工具，让一切假蜘蛛爬虫无处遁形！

**7. <a href="https://www.wbolt.com/tools/wp-codex?utm_source=wp&utm_medium=link&utm_campaign=magicpost">WP开发宝典</a>:** WordPress作为全球市场份额最大CMS，也为众多企业官网、个人博客及电商网站的首选。使用我们的开发宝典，快速了解其函数、过滤器及动作等作用和写法。

**8. <a href="https://www.wbolt.com/tools/robots-tester?utm_source=wp&utm_medium=link&utm_campaign=magicpost">robots.txt测试工具</a>:** 标准规范的robots.txt能够正确指引搜索引擎蜘蛛爬取网站内容。反之，可能让蜘蛛晕头转向。借助我们的robots.txt检测工具，校正您所写的规则。

**9. <a href="https://www.wbolt.com/tools/theme-detector?utm_source=wp&utm_medium=link&utm_campaign=magicpost">WordPress主题检测器</a>:** 有时候，看到一个您为之着迷的WordPress网站。甚是想知道它背后的主题。查看源代码定可以找到蛛丝马迹，又或者使用我们的小工具，一键查明。


== Installation ==

方式1：在线安装(推荐)
1. 进入WordPress仪表盘，点击`插件-安装插件`，关键词搜索`MagicPost`，找搜索结果中找到`MagicPost`插件，点击`现在安装`；
2. 安装完毕后，启用`MagicPost`插件.
3. 通过`Magicpost`->`插件设置` 完成插件模块启用配置即可使用。

方式2：上传安装

FTP上传安装
1. 解压插件压缩包`magicpost.zip`，将解压获得文件夹上传至wordpress安装目录下的 `/wp-content/plugins/` 目录.
2. 访问WordPress仪表盘，进入“插件”-“已安装插件”，在插件列表中找到“magicpost”插件，点击“启用”.
3. 通过`Magicpost`->`插件设置` 完成插件模块启用配置即可使用。

仪表盘上传安装
1. 进入WordPress仪表盘，点击`插件-安装插件`；
2. 点击界面左上方的`上传按钮`，选择本地提前下载好的插件压缩包`magicpost.zip`，点击`现在安装`；
3. 安装完毕后，启用`Magicpost`插件；
4. 通过`Magicpost`->`插件设置` 完成插件模块启用配置即可使用。

关于本插件，你可以通过阅读<a href="https://www.wbolt.com/magicpost-plugin-documentation.html?utm_source=wp&utm_medium=link&utm_campaign=magicpost" rel="friend" title="插件教程">Magicpost插件教程</a>学习了解插件安装、设置等详细内容。

== Frequently Asked Questions ==

== Screenshots ==

1. 定时发布功能截图.
2. 文章搬家功能截图.
3. HTML清理功能截图.
4. 下载管理功能截图.
5. 社交分享功能截图.
6. 内容目录功能截图.
7. 模块启用设置截图.

== Changelog ==

= 1.3.1 =
* 修正与付费阅读插件(WPVK)最新版搭配实现付费下载时异常。

= 1.3.0 =
* 分享模块：在移动端实现使用更完善的浏览器自身分享功能，在微信端打开时提供提示浮层引导使用微信自身分享功能。
* 新增Pro专享功能TabBar组件：在分享组件提供配置开关，TabBar提供有点赞，微海报，分享及TOC几个标签选项。
* TOC模块：逻辑细节优化 - 保留文章段落标题原有的锚点（若有）；移动端TabBar提供专门滑出层交互。
* 修正插件设置中分享模块配置logo图片等的功能异常。

= 1.2.2 =
* 修复分享短代码xss安全问题。

= 1.2.1 =
* 修复更换域名插件无法使用问题。

= 1.2.0 =
* 新增TOC内容目录模块；
* 基于编码规范进一步优化PHP代码；
* 优化PHP代码以提升性能；
* 优化PHP代码以增强代码安全性。

= 1.1.4 =
* 新增更新文章发布时间为当前时间支持；
* 新增谷歌翻译免费API接口和百度翻译API接口；
* 新增更多文章搬家筛选条件；
* 修复HTML代码清理弹窗样式错位；
* 解决PHP错误提示问题；
* 其他已知问题和bug修复。

= 1.1.3 =
* 优化正则表达式搜索替换及增加标签及内容搜索替换；
* 修正部分标签属性无法删除的bug；
* 修正下载浮动栏标题读取错误bug;
* 修正社交分享功能开关无效bug；
* 新增自定义下载方式（pro)；
* 其他前端js方法更新。

= 1.1.2 =
* 修复定时发布失败无法重新加入任务bug；
* 修复社交分享短代码无效bug；
* 增加阿里网盘分享支持；
* 文章搬家按分类及按标签搜索结果新增批量选中当前页和所有页支持；
* 优化HTML代码优化文章编辑操作弹窗界面样式。

= 1.1.1 =
* 修复与付费内容插件兼容性问题；
* 补充文章翻译模块图标。

= 1.1.0 =
* 新增文章翻译功能模块；
* 重写文章搬家标签功能；
* 优化微海报功能模块，增加海报样式及修复QQ空间分享bug；
* 修复更新提示链接无法点击bug。

= 1.0.1 =
* 新增手动添加文章至定时列表失败提示；
* 修复HTML代码清理自定义标签属性bug。

= 1.0.0 =
* MagicPost首个版本发布。
