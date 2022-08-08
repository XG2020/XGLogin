<?php
include 'common.php';
if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}
$option = XGLogin_Plugin::getoptions();
$rememberName = htmlspecialchars(Typecho_Cookie::get('__typecho_remember_name'));
Typecho_Cookie::delete('__typecho_remember_name');
$header = '<link rel="stylesheet" href="' . Typecho_Common::url('normalize.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<link rel="stylesheet" href="' . Typecho_Common::url('grid.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<link rel="stylesheet" href="' . Typecho_Common::url('style.css?v=' . $suffixVersion, $options->adminStaticUrl('css')) . '">
<!--[if lt IE 9]>
<script src="' . Typecho_Common::url('html5shiv.js?v=' . $suffixVersion, $options->adminStaticUrl('js')) . '"></script>
<script src="' . Typecho_Common::url('respond.js?v=' . $suffixVersion, $options->adminStaticUrl('js')) . '"></script>
<![endif]-->';
?>
<!DOCTYPE HTML>
<html class="no-js">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="renderer" content="webkit">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php _e('%s - %s - Powered by Typecho', $menu->title, $options->title);?></title>
        <meta name="robots" content="noindex, nofollow">
      <?= $header;?>
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
	</head>
    <body class="body-100">
    <!--[if lt IE 9]>
        <div class="message error browsehappy" role="dialog">当前网页 <strong>不支持</strong> 你正在使用的浏览器. 为了正常的访问, 请 <a href="http://browsehappy.com/">升级你的浏览器</a>.</div>
    <![endif]-->
	<div class="typecho-login-wrap">
    <div class="typecho-login">
        <!--<h1><a href="#" class="i-logo">Typecho</a></h1>-->
		<img src="https://xggm.top/logo.png" height="80px">
		<div id="qrlogin">
			<h3 id="type_title">扫码登录</h3>
			<div id="qrimg"></div>
			<p id="msg">正在获取二维码...</p><hr/>
			<?php if($option->off == '0'):?>
			<button type="submit" class="btn primary" onclick="$('#qrlogin').hide();$('#login').show();">账号密码登录
			</button>
			<?php endif; ?>
		</div>
		<?php if($option->off == '0'):?>
		<div id="login" style="display:none;">
			<form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form" id="login">
				<p>
					<label for="name" class="sr-only">用户名</label>
					<input type="text" id="name" name="name" value="" placeholder="用户名" class="text-l w-100" autofocus />
				</p>
				<p>
					<label for="password" class="sr-only">密码</label>
					<input type="password" id="password" name="password" class="text-l w-100" placeholder="密码" />
				</p>
				<p class="submit">
				<input type="hidden" name="referer" value="<?php echo htmlspecialchars($request->get('referer')); ?>" />
				<button type="submit" class="btn primary">立即登录</button>
				<button type="button" class="btn primary" onclick="$('#qrlogin').show();$('#login').hide();">扫码登录</button>
				</p>
				<p>
					<label for="remember"><input type="checkbox" name="remember" class="checkbox" value="1" id="remember" /> 下次自动登录</label>
				</p>
			</form>
		</div>
		<?php endif; ?>
        <p class="more-link"> <a href="<?php $options->siteUrl(); ?>">返回首页</a> </p>
    </div>
</div>
<?php include 'common-js.php';include 'footer.php';?>
<script>
var type = <?=$option->type?>;
function getresult() {
		var api = "<?= XGLogin_Plugin::tourl('XGLogin/getresult');?>";
		post = 'login=1&qrsig=' + data['qrsig'];
		$.ajax({
			url: api,
			aycnc: false,
			type: 'POST',
			data: post,
			dataType: 'json',
			success: function (data) {
				if(data.login && data.login.code == 10000){
					alert(data.login.msg);
					window.clearInterval(window.id);
					window.location.href = data.login.url+'?token='+(data.login.token);
				}else if(data.login && data.login.code == 0){
					alert(data.login.msg);
					$('#msg').html(data.login.msg);
					window.clearInterval(window.id);
				}else{
					$('#msg').html(data.msg);
				}
			},
			error: function () {
				console.log('登录结果获取失败！');
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
				window.type = type;
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

$(document).ready(function () {
		getqrocde('qq');
});
</script>
