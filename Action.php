<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {

    exit;
}

class XGLogin_Action extends Typecho_Widget
{

    /* 重置当前用户绑定数据 */
    public function reset()
    {
        require_once __TYPECHO_ROOT_DIR__ . __TYPECHO_ADMIN_DIR__ . 'common.php';
        $res = new Typecho_Response();
        $ret = [];

        if ($user->haslogin()) {
            // 获取当前用户名
            $name = $user->__get('name');

            // 获取插件配置
            $options = XGLogin_Plugin::getoptions();
            $key     = $options->key;

            // 处理数据
            $filepath = XGLogin_Plugin::PLUGIN_PATH . $key . '.db';
            $data     = XGLogin_Plugin::getuser();

            $data[$name]['qq'] = '';

            @file_put_contents($filepath, XGLogin_Plugin::authcode(serialize($data), 'ENCODE', $key));
            $ret['code'] = 200;
            $ret['msg']  = '当前用户绑定信息重置成功';
        } else {
            $ret['msg'] = 'what are you doing?';
        }
        $res->throwJson($ret);
    }

    /* 跳转验证登录 */
    public function login()
    {
        $req   = new Typecho_Request();
        $res   = new Typecho_Response();
        $token = base64_decode(urldecode($req->get('token')));

        // 获取插件配置
        $options = Helper::options()->plugin(XGLogin_Plugin::PLUGIN_NAME);
        $key     = $options->key;

        // 解密Token
        $data = @json_decode(XGLogin_Plugin::authcode($token, 'DECODE', $key), true);

        $user_qr = XGLogin_Plugin::getuser();

        if (is_array($data) && isset($data) && $user_qr[$data['user']][$data['type']] === $data['uin'] && time() < $data['time']) {
            $hashValidate = true;
        }

        $name = $data['user'];
        $db   = Typecho_Db::get();

        $user = $db->fetchRow($db->select()->from('table.users')->where((strpos($name, '@') ? 'mail' : 'name') . ' = ?',
            $name)->limit(1));

        if ($user && $hashValidate) {
            $authCode         = function_exists('openssl_random_pseudo_bytes') ? bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
            $user['authCode'] = $authCode;

            Typecho_Cookie::set('__typecho_uid', $user['uid'], $expire);
            Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), $expire);

            //更新最后登录时间以及验证码
            $db->query($db->update('table.users')->expression('logged',
                'activated')->rows(['authCode' => $authCode])->where('uid = ?', $user['uid']));

            /** 压入数据 */
            $this->push($user);
            $this->_user     = $user;
            $this->_hasLogin = true;

            echo 'success';
        }
        $res->redirect(Helper::options()->adminUrl);
    }

    /* 二维码授权绑定 */
    public function authbind()
    {
        $path = XGLogin_Plugin::PLUGIN_PATH . 'views/authbind.php';
        require_once $path;
    }

    /* 获取登录二维码 */
    public function getqrcode()
    {
        $res    = new Typecho_Response();
        $req    = new Typecho_Request();
        $qrcode = [];
        $api             = 'https://ssl.ptlogin2.qq.com/ptqrshow?appid=549000912&e=2&l=M&s=3&d=72&v=4&t=0.60651792' . time() . '&daid=5&pt_3rd_aid=0';
        $paras['header'] = 1;
        $resp            = self::get_curl($api, $paras);
        preg_match('/qrsig=([0-9a-z]+);/', $resp, $matches);
        $arr             = explode("\r\n\r\n", $resp);
        $qrcode['qrsig'] = $matches[1];
        $qrcode['data']  = base64_encode(trim($arr['1']));
        $res->throwJson($qrcode);
    }

    /* 获取登录结果 */
    public function getresult()
    {
        $res   = new Typecho_Response();
        $req   = new Typecho_Request();
        $ret   = [];
        $qrsig = $req->get('qrsig');
        $login = $req->get('login');
        if ($qrsig) {
            $api             = 'https://ssl.ptlogin2.qq.com/ptqrlogin?u1=https://qzs.qq.com/qzone/v5/loginsucc.html&ptqrtoken=' . self::getqrtoken($qrsig) . '&ptredirect=0&h=1&t=1&g=1&from_ui=1&ptlang=2052&action=0-2-' . time() . '&js_ver=22052613&js_type=1&login_sig=&pt_uistyle=40&aid=549000912&daid=5&ptdrvs=&sid=&&o1vId=';
            $paras['cookie'] = 'qrsig=' . $qrsig . ';';
            $body            = self::get_curl($api, $paras);
            if (preg_match("/ptuiCB\('(.*?)'\)/", $body, $arr)) {
                $r = explode("','", str_replace("', '", "','", $arr[1]));
                if ($r[0] == 0) {
                    preg_match('/uin=(\d+)&/', $body, $uin);
                    $ret['code']         = 200;
                    $ret['data']['uin']  = $uin[1];
                    $ret['data']['type'] = 'qq';
                    $ret['msg']          = 'QQ登录成功';
                } elseif ($r[0] == 65) {
                    $ret['msg'] = '登录二维码已失效，请刷新重试！';
                } elseif ($r[0] == 66) {
                    $ret['msg'] = '请使用手机QQ扫码登录';
                } elseif ($r[0] == 67) {
                    $ret['msg'] = '正在验证二维码...';
                } else {
                    $ret['msg'] = '未知错误001，请刷新重试！';
                }
            } else {
                $ret['msg'] = '登录结果获取失败';
            }
        } else {
            $ret['msg'] = '请求参数错误，请刷新重试！~~';
        }
        // ------------------------
        if ($login && $ret['code'] == 200) { //验证登录
            // 获取插件配置
            $options = XGLogin_Plugin::getoptions();
            $key     = $options->key;

            // 处理数据
            $filepath = XGLogin_Plugin::PLUGIN_PATH . $key . '.db';
            $data     = unserialize(XGLogin_Plugin::authcode(file_get_contents($filepath), 'DECODE', $key));

            $ret['login']['msg']  = 'Fail';
            $ret['login']['code'] = 0;

            foreach ($data as $user => $arr) {
                if ($arr[$ret['data']['type']] == $ret['data']['uin']) {

                    // 生成登录有效地址
                    $time  = time() + 15; //  URL失效时间
                    $token = XGLogin_Plugin::authcode(json_encode([
                        'user' => $user,
                        'time' => $time,
                        'type' => $ret['data']['type'],
                        'uin'  => $ret['data']['uin']
                    ]), 'ENCODE', $key);

                    $ret['login']['token'] = base64_encode($token);
                    $ret['login']['code']  = 10000;
                    $ret['login']['user']  = $user;
                    $ret['login']['msg']   = '登录成功！';
                    $ret['login']['url']   = XGLogin_Plugin::tourl('XGLogin/login');
                    break;
                }
            }
        }
        $res->throwJson($ret);
    }

    /* 绑定授权信息 */
    public function bind()
    {
        require_once __TYPECHO_ROOT_DIR__ . __TYPECHO_ADMIN_DIR__ . 'common.php';
        $res = new Typecho_Response();
        $req = new Typecho_Request();
        $ret = [];

        if ($user->haslogin()) {
            // 获取当前用户名
            $name = $user->__get('name');

            // 获取插件配置
            $options = XGLogin_Plugin::getoptions();
            $key     = $options->key;

            // 获取请求参数
            $type = $req->get('type');
            $uin  = $req->get('uin');

            // 处理数据
            $filepath = XGLogin_Plugin::PLUGIN_PATH . $key . '.db';
            $data     = XGLogin_Plugin::getuser();

            // 判断当前UIN是否已经绑定
            foreach ($data as $name_ => $arr) {
                if ($arr[$type] == $uin && $name_ != $name) {
                    $ret['code'] = 201;
                    $ret['msg']  = ($type == 'QQ') . '已绑定另一账户，绑定失败';
                    $res->throwJson($ret);
                    break;
                }
            }

            $data[$name][$type] = $uin;
            @file_put_contents($filepath, XGLogin_Plugin::authcode(serialize($data), 'ENCODE', $key));
            $ret['code'] = 200;
            $ret['msg']  = ($type == 'QQ') . '登录绑定成功';
        } else {
            $ret['msg'] = 'what are you doing?';
        }
        $res->throwJson($ret);
    }

    /** QQ空间Token算法*/
    public static function getqrtoken($qrsig)
    {
        $len  = strlen($qrsig);
        $hash = 0;
        for ($i = 0; $i < $len; $i++) {
            $hash += (($hash << 5) & 2147483647) + ord($qrsig[$i]) & 2147483647;
            $hash &= 2147483647;
        }
        return $hash & 2147483647;
    }

    /** Curl单例封装函数 */
    public static function get_curl($url, $paras = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($paras['ctime']) { // 连接超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $paras['ctime']);
        }
        if ($paras['rtime']) { // 读取超时
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $paras['rtime']);
        }
        if ($paras['post']) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paras['post']);
        }
        if ($paras['header']) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($paras['cookie']) {
            curl_setopt($ch, CURLOPT_COOKIE, $paras['cookie']);
        }
        if ($paras['refer']) {
            if ($paras['refer'] == 1) {
                curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
            } else {
                curl_setopt($ch, CURLOPT_REFERER, $paras['refer']);
            }
        }
        if ($paras['ua']) {
            curl_setopt($ch, CURLOPT_USERAGENT, $paras['ua']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT,
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36');
        }
        if ($paras['nobody']) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

}
