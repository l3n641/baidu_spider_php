<?php

require_once './vendor/autoload.php';
require_once './functions.php';
require_once './middleware.php';

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

/**
 * @param $keyword 百度要查找的关键词
 * @param $pg 从第几个页面开始解析 从0 开始(0代表第一个页面)
 * @param int $rn 每个页面出现多少关键词 默认是10 ,可以选择的规格是小于50
 * @param bool $save_path 是否保存 百度搜索的html 内容,默认不报错,如果传入一个路径的话,就保存,方便调试,线上环境可以关闭
 * @return array|bool 链接信息的数组或者bool 数组格式为[  ["url"=>"url","title"=>'title"] ]
 * @throws \GuzzleHttp\Exception\GuzzleException
 */


function getgjcpm($keyword, $pg, $rn = 10, $save_path = false)
{

    try {
        $enable_proxy = false;
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());

        if ($save_path) {
            $stack->push(BaiduSearchLog::get_handler($keyword, $pg, $rn, $save_path));
            $stack->push(BaiduRequestHeader::get_handler());
            $stack->push(BaiduRequestHeader::get_save_cookie_handler());

        }

        if ($enable_proxy) {
            $proxy_url = "http://120.79.85.144/index.php/api/entry?method=proxyServer.tiqu_api_url&packid=1&fa=0&fetch_key=&groupid=0&qty=1&time=1&port=2&format=json&ss=5&css=&ipport=1&et=1&pro=&city=&dt=1&auth=1&ipnum=10&userpwd=1&auth_key=fnp63K6ncap_p3XYhHWpsX6iiauDfHndsrGGabmYkGqXfn_NxtGUrZekcZ4&usertype=22";
            $stack->push(FeizhuProxy::get_handler($proxy_url));

        }
        $client = new Client(['handler' => $stack]);

        $url = "http://www.baidu.com/s";
        $response = $client->get($url, [
                'query' => ['wd' => $keyword, 'pn' => ($pg * 10), 'oq' => $keyword, 'tn' => 'baiduhome_pg', 'rn' => $rn],
                'connect_timeout' => 10,
            ]
        );
        sleep(rand(2, 3)); //睡眠时间,调整爬虫 爬取频率

        $body = $response->getBody();
        $html = $body->getContents();
        if (is_captcha_page($html)) {
            return false;
        }

        if (strpos($html, '很抱歉，没有找到') !== false || is_not_found_keyword_page($html)) {
            return false;
        }

        $urls = parse_section_header($html);
        var_dump(count($urls));
        return $urls;
    } catch (\GuzzleHttp\Exception\ConnectException  $connect_exception) {
        // $proxy_data = get_proxy($proxy_url, './proxy/', true);
        print_r($connect_exception->getMessage());
        var_dump('444');
    } catch (Exception $exception) {
        var_dump('xxx');
    }
}


/**
 * @param $seach_page 要收集多少个页面
 * @param $rn 每个页面出现多少条记录
 */
function main($seach_page, $rn)
{
    $keywods = get_keywords();
    $session_id = time();
    $log_file = $session_id . ".log";
    $save_path = './html/' . $session_id . "/";
    foreach ($keywods as $x => $data) {

        if ($data["gjc"] !== "") {

            for ($page = 0; $page < $seach_page; $page++) {
                $pmurl_count = 0;
                $urls_info = getgjcpm($data["gjc"], $page, $rn, $save_path);
                if ($urls_info) {
                    $urls = [];
                    foreach ($urls_info as $info) {
                        $urls[] = $info['url'];
                    }
                    $gjcpmlj = "";
                    $gjcpmlj .= getgjcpmurl($urls);
                    $gjcpmlj = rtrim($gjcpmlj, "^");

                    $pmurl = explode("^", $gjcpmlj);
                    if ($pmurl) {
                        save_result($data["gjc"], $pmurl, './logs/', $session_id . ".json");

                    }
                    $pmurl_count = count($pmurl);

                }

                printf("关键词:%s, 深度为:%d, 每个页面计划收集%d条数据, 当前页面:%d, 当前页面链接数:%d , 解析后的链接数量:%d\r\n",
                    $data["gjc"], $seach_page, $rn, $page, count($urls_info), $pmurl_count);

            }

        }
    }


}


main(1, 50);