<?php


/**飞猪代理中间件
 * Class FeizhuProxy
 */
class FeizhuProxy
{

    /**获取飞猪的代理配置
     * @return mixed
     * @throws ErrorException
     */
    protected static function get_proxy_seeting($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $body = curl_exec($ch);
            $datas = json_decode($body, true);
            if ($datas['code'] == 0) {

                return $datas['data'];
            } else {
                throw new  ErrorException($datas['msg']);

            }
        } catch (Exception $e) {
            throw new  ErrorException($e->getMessage());
        }


    }

    /**获取代理配置
     * @param $cache_path
     * @param string $cache_file_name
     * @param int $prex_expire
     * @return mixed
     * @throws ErrorException
     */
    public static function get_proxy($proxy_url, $cache_path, $update_cached = false, $cache_file_name = "proxy", $prex_expire = 10)
    {

        if (!file_exists($cache_path)) {
            mkdir($cache_path, 0777, true);
        }
        $cache_file_path = $cache_path . $cache_file_name;
        if (file_exists($cache_file_path) && !$update_cached) {
            $data = json_decode(file_get_contents($cache_file_path), true);
            $date = new DateTime($data['ExpireTime'], new DateTimeZone('Asia/Shanghai'));
            if ($data && $date->getTimestamp() > $prex_expire + time()) {
                return $data;
            }
        }

        $proxy_datas = static:: get_proxy_seeting($proxy_url);
        $proxy_data = $proxy_datas[0];

        file_put_contents($cache_file_path, json_encode($proxy_data));
        return $proxy_data;

    }


    protected static function basic_auth_header($username, $password)
    {
        $auth = sprintf("%s:%s", $username, $password);
        $auth = base64_encode($auth);
        return "Basic " . $auth;

    }


    public static function get_handler($proxy_url)
    {
        $proxy_data = static::get_proxy($proxy_url, './proxy/');

        return function (callable $handler) use ($proxy_data) {
            return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $proxy_data) {
                $request = $request->withHeader('Proxy-Authorization', static::basic_auth_header($proxy_data['username'], $proxy_data['password']));
                $options['verify'] = false;
                $options['proxy'] = "socks5://" . $proxy_data['IP'];
                return $handler($request, $options);
            };
        };

    }
}

/**百度搜索日志中间件
 * Class BaiduSearchLog
 */
class BaiduSearchLog
{

    public static function get_handler($keyword, $pg, $rn, $save_path)
    {
        return function (callable $handler) use ($keyword, $pg, $rn, $save_path) {
            return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $keyword, $pg, $rn, $save_path) {
                $promise = $handler($request, $options);
                return $promise->then(
                    function (Psr\Http\Message\ResponseInterface $response) use ($handler, $keyword, $pg, $rn, $save_path) {
                        if (!file_exists($save_path)) {
                            mkdir($save_path, 0777, true);
                        }

                        $body = $response->getBody();
                        $html = $body->getContents();
                        file_put_contents($save_path . $keyword . "_" . $pg . '.html', $html);
                        $urls_info = static::parse_section_header($html);
                        static::spider_log($save_path, ["关键词" => $keyword, "对应了链接数" => count($urls_info), "页面" => $pg,]);

                        $body->rewind();
                        return $response;
                    }
                );
            };
        };
    }


    /**解析百度搜索页面的给个session的头部信息
     * @param $html
     * @return array
     */
    protected static function parse_section_header($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xPath = new DOMXPath($dom);
        $elements = $xPath->query('//*[@id]/h3/a[1]');

        $urls = [];
        foreach ($elements as $element) {
            $data['url'] = $element->getAttribute('href');
            $data['title'] = $element->nodeValue;
            $urls[] = $data;
        }
        return $urls;
    }


    /**日志
     * @param $level 日志等级
     * @param $args 日志参数
     */
    protected static function spider_log($path, $args)
    {
        if (!file_exists($path)) {
            mkdir($path);
        }
        $file_name = 'debug.log';

        $path = $path . $file_name;

        $args_str = "";
        foreach ($args as $key => $value) {
            $args_str = $args_str . $key . ": " . $value . ", ";
        }
        $log = date("Y-m-d H:i:s") . ", " . $args_str . "\r\n";
        file_put_contents($path, $log, FILE_APPEND);

    }


}


class BaiduRequestHeader
{


    public static function get_handler()
    {

        return function (callable $handler) {
            return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler) {
                $accept = "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9";
                $request = $request->withHeader('User-Agent', static::get_user_agent())
                    ->withHeader('Accept', $accept)
                    ->withHeader("Accept-Language", "zh-CN,zh;q=0.9")->withHeader("Cookie", static::get_baidu_cookies());


                return $handler($request, $options);
            };
        };


    }


    /**
     * 获取http 的 user_agent
     */
    public static function get_user_agent()
    {
        $ua_list = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36',
        ];
        return $ua_list[mt_rand(0, count($ua_list) - 1)];
    }

    /**
     * 获取获取百度cookie,以后可以尝试从文件里面读取cookie,这样你更新cookie的时候,只要上传那个文件就可以.
     */

    public static function get_baidu_cookies()
    {
        $baidu_id = $uniqid = md5(uniqid());
        return sprintf("BAIDUID=%s:FG=1; BIDUPSID=%s; PSTM=%s", $baidu_id, $baidu_id, time());
    }


}