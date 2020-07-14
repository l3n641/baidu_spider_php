<?php

require_once './vendor/autoload.php';
require_once './functions.php';

use GuzzleHttp\Client;


function getgjcpm($keyword, $pg, $rn = 10, $save_path = false)
{

    $url = "http://www.baidu.com/s";

    $client = new Client([
    ]);

    $response = $client->get($url, [
            'query' => ['wd' => $keyword, 'pn' => ($pg * 10), 'oq' => $keyword, 'tn' => 'baiduhome_pg', 'rn' => $rn],
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => get_user_agent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Language' => 'zh-CN,zh;q=0.9',
                "Cookie" => get_baidu_cookies(),
            ],
        ]
    );
    sleep(rand(2, 3));

    $body = $response->getBody();
    $html = $body->getContents();
    if ($save_path) {
        if (!file_exists($save_path)) {
            mkdir($save_path, 0777, true);
        }
        file_put_contents($save_path . $keyword . "_" . $pg . '.html', $html);
    }

    if (is_captcha_page($html)) {
        return false;
    }

    if (strpos($html, '很抱歉，没有找到') !== false) {
        return false;
    }

    $urls = parse_section_header($html);
    return $urls;
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
                    $pmurl_count = count($pmurl);

                }

                printf("关键词:%s, 深度为:%d, 每个页面计划收集%d条数据, 当前页面:%d, 当前页面链接数:%d , 解析后的链接数量:%d\r\n",
                    $data["gjc"], $seach_page, $rn, $page, count($urls_info), $pmurl_count);
                spider_log('info', ["关键词" => $data["gjc"], "对应了链接数" => count($urls_info), "页面" => $page, "解析链接数" => $pmurl_count], $log_file);


            }

        }
    }


}

main(1, 50);