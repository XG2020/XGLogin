<?php
require_once __TYPECHO_ROOT_DIR__.__TYPECHO_ADMIN_DIR__.'common.php';
// 获取当前用户名
$name = $user->__get('name');
$data = XGLogin_Plugin::getuser();
$option = XGLogin_Plugin::getoptions();
$group = $user->__get('group');

$qq = empty($data[$name]['qq'])?'未绑定':$data[$name]['qq'];

if($group != 'administrator' && !$option->users){ //非管理员且[非管理员启用]处于否
	throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}
?>
<!DOCTYPE HTML>
<html class="no-js">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="renderer" content="webkit">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>XGLogin - 扫描登录授权绑定</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="stylesheet" href="<?=__TYPECHO_ADMIN_DIR__?>css/normalize.css?v=17.10.30">
		<link rel="stylesheet" href="<?=__TYPECHO_ADMIN_DIR__?>css/grid.css?v=17.10.30">
		<link rel="stylesheet" href="<?=__TYPECHO_ADMIN_DIR__?>css/style.css?v=17.10.30">
		<?php 
            $url = Helper::options()->pluginUrl . '/XGAdmin/static/';
            $diycss = Typecho_Widget::widget('Widget_Options')->plugin('XGAdmin')->diycss;
            $skin = Typecho_Widget::widget('Widget_Options')->plugin('XGAdmin')->bgfengge;
            if ($skin == 'kongbai') {
                $hed = $hed . '<style>' . $diycss . '</style>';
            } else {
                if ($skin == 'heike') {
                    $hed = $hed . '<link rel="stylesheet" href="' . $url . 'skin/' . $skin . '.css?20220805">';
                } else {
                    $bgUrl = Typecho_Widget::widget('Widget_Options')->plugin('XGAdmin')->bgUrl;
                    $zidingyi = "";
                    if ($bgUrl) {
                        $zidingyi = "<style>body,body::before{background-image: url(" . $bgUrl . ")}</style>";
                    }
                    $hed = $hed . '<link rel="stylesheet" href="' . $url . 'skin/' . $skin . '.css?20220805">' . $zidingyi;
                }
            }
            echo $hed;
        ?>
		<!--[if lt IE 9]>
		<script src="/admin/js/html5shiv.js?v=17.10.30"></script>
		<script src="/admin/js/respond.js?v=17.10.30"></script>
		<![endif]-->    
</head>
    <body class="body-100">
    <!--[if lt IE 9]>
        <div class="message error browsehappy" role="dialog">当前网页 <strong>不支持</strong> 你正在使用的浏览器. 为了正常的访问, 请 <a href="http://browsehappy.com/">升级你的浏览器</a>.</div>
    <![endif]-->
	<div class="typecho-login-wrap">
    <div class="typecho-login">
        <!--<h1><a href="#" class="i-logo">Typecho</a></h1>-->
        <img src="https://xggm.top/logo.png" height="80px">
		<div class="qrlogin">
			<h3>用户授权：<?=$name?></h3>
			<p>已绑定QQ：<?=$qq?></p>
			<div id="qrimg" style=""></div>
			<p id='msg'>请使用QQ扫码...</p><hr/>
			<button type="submit" class="btn primary" onclick="reset()">重置绑定数据</button>
		</div>
        <p class="more-link"> <a href="/">返回首页</a> </p>
    </div>
</div>
<script src="<?=__TYPECHO_ADMIN_DIR__?>js/jquery.js?v=17.10.30"></script>
<script src="<?=__TYPECHO_ADMIN_DIR__?>js/jquery-ui.js?v=17.10.30"></script>
<script src="<?=__TYPECHO_ADMIN_DIR__?>js/typecho.js?v=17.10.30"></script>
<script>
	var data = {};
	function bind(type,uin){
		var api = "<?= XGLogin_Plugin::tourl('XGLogin/bind');?>";
		$.ajax({
			url: api,
			type: 'POST',
			data: 'type=' + type+'&uin='+uin,
			dataType: 'json',
			success: function (data) {
				alert(data.msg);
				window.location.reload();
			},
			error: function () {
				alert('绑定失败！~~');
			}
		});
	}
	function getqrocde(type) {
		var api = "<?= XGLogin_Plugin::tourl('XGLogin/getqrcode');?>";
		$.ajax({
			url: api,
			type: 'POST',
			data: 'type=' + type,
			dataType: 'json',
			success: function (data) {
				window.data = data;
				$('#qrimg').html('<img style="width:147px;height:147px;" src="data:image/png;base64,' + data.data + '" >');
				// 开始循环请求结果
				if(window.id){
					window.clearInterval(window.id);
				}
				window.id = setInterval(getresult, 3000);
			},
			error: function () {
				alert('二维码获取失败！');
			}
		});
	}
	function reset(){
		var api = "<?= XGLogin_Plugin::tourl('XGLogin/reset');?>";
		$.ajax({
			url: api,
			aycnc: false,
			type: 'POST',
			dataType: 'json',
			success: function (data) {
				if(data.code == 200){
					alert(data.msg);
				}
				$("#msg").html(data.msg);
				window.location.reload();
			},
			error: function () {
				console.log('falil!~~');
			}
		});
	}
	function getresult() {
		var api = "<?= XGLogin_Plugin::tourl('XGLogin/getresult');?>";
		post = 'qrsig=' + data['qrsig'];
		$.ajax({
			url: api,
			aycnc: false,
			type: 'POST',
			data: post,
			dataType: 'json',
			success: function (data) {
				if(data.code == 200){
					bind(window.type,data.data.uin);
					window.clearInterval(window.id);
				}
				$("#msg").html(data.msg);
			},
			error: function () {
				console.log('登录结果获取失败！');
			}
		});
	}
	$(document).ready(function () {
		// 默认QQ
		window.type = "qq";
		window.nums = 0;
		$("#qq_auth").hide();
		// 获取QQ登录二维码
		getqrocde(type);
	});

</script>
    </body>
</html>
