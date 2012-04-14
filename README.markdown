# Hacklog Remote Attachment Upyun

##WordPress插件信息

------------------------------------------------------------

* Contributors: ihacklog
* Donate link: http://ihacklog.com/donate
* Tags: attachment,manager,admin,images,thumbnail,ftp,remote
* Requires at least: 3.3
* Tested up to: 3.3.1
* Stable tag: 1.4.2

------------------------------------------------------------

Adds remote attachments support for your WordPress blog.

------------------------------------------------------------

### Description
Features: Adds remote attachments support for your WordPress blog.

use this plugin, you can upload any files to remote ftp servers(be aware that your FTP server must has Apache or other HTTP server daemon) in WordPress.

* Support both single user site AND multisite.
* support upload files to remote FTP server.
* support delete files on remote FTP server.
* works just like the files are saved on your local server-_-.
* with this plugin,you can move all your local server files to remote server.
* after you've uninstall this plugin,you can move remote server files to local server if you'd like to do so.

For MORE information,please visit the [plugin homepage](http://ihacklog.com/?p=5204 "plugin homepage") for any questions about the plugin.

[installation guide](http://ihacklog.com/?p=4993 "installation guide") 
如果你只是需要远程附件功能的支持，而并不想使用又拍云的服务，可以尝试下使用
[Hacklog Remote Attachment <支持FTP + HTTP服务器>](http://ihacklog.com/?p=5001 "plugin homepage")

* version 1.1.0 added compatibility with watermark plugins
* version 1.2.0 added duplicated file checking,so that the existed remote files will not be overwrote.
* version 1.2.1 fixed the bug when uploading new theme or plugin this plugin may cause it to fail.

* 1.1.0 增加与水印插件的兼容性，使上传到远程服务器的图片同样可以加上水印
* 1.2.0 增加重复文件检测，避免同名文件被覆盖。更新和完善了帮助信息。
* 1.2.1 修正在后台上传主题或插件时的bug.
* 1.2.7 增加三种http数据发送方式支持远程附件(curl,fsockopen,streams),方便没有curl扩展支持的朋友.
* 1.2.8 增加对xmlrpc支持(支持通过Windows Live Writer 上传图片时自动上传到Upyun服务器)
* 1.2.9 修复Windows Live Writer 上传图片时url不正确的bug
* 1.3.0 修复首次使用插件时，又拍云空间使用量为0时显示“测试连接失败”的bug.增加更详细的错误信息提示。
* 1.4.0 增加form API上传支持，可上传100MB以内大小的文件（又拍云form API目前最大只支持100MB）

更多信息请访问[插件主页](http://ihacklog.com/?p=5001 "plugin homepage") 获取关于插件的更多信息，使用技巧等.
[安装指导](http://ihacklog.com/?p=4993 "安装指导") 

### Installation

1. Upload the whole fold `hacklog-remote-attachment` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin via `Settings` -> `Hacklog Remote Attachment` menu and it's OK now,you can upload attachments(iamges,videos,audio,etc.) to the remote FTP server.
4. If your have moved all your local server files to remote server,then you can `UPDATE THE DATABASE` so that all your attachments URLs will be OK.
You can visit [plugin homepage](http://ihacklog.com/?p=5001 "plugin homepage") for detailed installation guide.

### Screenshots


![截图1](hacklog-remote-attachment-upyun/raw/master/screenshot-1.png "截图1")



![截图2](hacklog-remote-attachment-upyun/raw/master/screenshot-2.png "截图2")


  



### Frequently Asked Questions
see 
[Hacklog Remote Attachment FAQ](http://ihacklog.com/?p=5001 "Hacklog Remote Attachment FAQ") 


### Upgrade Notice
#### 1.4.x upgrade to 1.4.2
增加空间TOKEN防盗链功能支持

#### 1.3.0 upgrade to 1.4.0
如果要使用**表单 API 功能**,注意在又拍云空间管理里面开启表单 API功能，并在插件后台选项中更新表单 API密匙. 


### Changelog

### 1.4.2
增加空间TOKEN防盗链功能支持

#### 1.4.1
* added: HTML5 file info detect.

#### 1.4.0
* added: form API uploading support.

#### 1.3.0
* fixed: the bug that say "Connection failed" when the bucket is empty in the first time use this plugin.
* improved: get verbose error message displayed.

#### 1.2.9
* fixed: Windows Live Writer file uploading bug(url incorrect).

#### 1.2.8
* added: xmlrpc support (when use Windows Live Writer or other client via xmlrpc upload attahcment,the attachment will auto uploaded to remote FTP server )

#### 1.2.7
* added: curl,fsockopen,streams support for http communication.

#### 1.2.6
* added: duplicated thumbnail filename (this things may happen when crop is TRUE)

#### 1.2.5
* changed: use simple xor cypher instead of using blow_fish

#### 1.2.4
* fixe: curl connection timeout will return '',change the message to more detailed one[class UpYun].

#### 1.2.3
* changed: load_textdomain param 3 uses basename(dirname()) instead of plugin_basename 
* fixed: trim spaces on options
* improved: Prevent direct access to files
* changed: uses upyun HTTP REST API to create and delete directory,files
* improved: protect your API password with the strong blowfish cypher.
* improved: the plugin settings page can show you the space useage of your remote bucket.

#### 1.2.2
* ported from Hacklog Remote Attachment












