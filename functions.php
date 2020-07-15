<?php

/**获取关键词
 * @return array|mixed
 */
function get_keywords()
{
    $zdbg = "www.yhel.com_zzgjc";
    $bgurl = "http://122.114.151.238";
    $contents = file_get_contents($bgurl . "/data/" . $zdbg . ".json");
    if ($contents) {
        $keywords = json_decode($contents, true);
        return $keywords;
    }
    return [];
}

/**解析百度页面每个章节的尾部信息
 * @param $html
 * @return array
 */
function parse_section_bottom($html)
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xPath = new DOMXPath($dom);
    $elements = $xPath->query('//*[@id]//div[@class="f13 c-gap-top-xsmall se_st_footer"]');

    $urls = [];
    foreach ($elements as $element) {
        $data_tools_list = $xPath->evaluate('./div', $element);
        $data = json_decode($data_tools_list[0]->getAttribute('data-tools'), true);
        $des_url_list = $xPath->evaluate('./a[@class="c-showurl c-color-gray"]/text()', $element);
        $data['des_url'] = $des_url_list[0]->wholeText ? parse_url($des_url_list[0]->wholeText)['path'] : "";
        $urls[] = $data;
    }
    return $urls;
}


/**解析百度搜索页面的给个session的头部信息
 * @param $html
 * @return array
 */
function parse_section_header($html)
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

/**判断这个页面是不是为百度的人机验证页面,如果是返回true
 * @param $html
 */
function is_captcha_page($html)
{

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xPath = new DOMXPath($dom);
    $elements = $xPath->query('/html/head/title/text()');
    if (count($elements) && $elements[0]->nodeValue == "百度安全验证") {
        return true;
    }

    $elements = $xPath->query('/html/body/a[starts-with(@href,"https://wappass.baidu.com/static/captcha/tuxing.html")]');
    if (count($elements)) {
        return true;
    }
    return false;


}


/**你自己的函数
 * @param $hrefsTag
 * @return string
 */
function getgjcpmurl($hrefsTag)
{
    date_default_timezone_set('PRC');

    $mh = curl_multi_init();
    foreach ($hrefsTag as $i => $ch) {
        $conn[$i] = curl_init($ch);
        curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($conn[$i], CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn[$i], CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn[$i], CURLOPT_TIMEOUT, 10);
        curl_setopt($conn[$i], CURLOPT_USERAGENT, get_user_agent());
        curl_multi_add_handle($mh, $conn[$i]);

    }
    do {

        curl_multi_exec($mh, $active);

    } while ($active);


    $pmlj = "";
    foreach ($hrefsTag as $i => $ch) {

        $info = curl_getinfo($conn[$i]);
        $retUrl = $info['url'];
        $pmlj .= $retUrl . "^";

    }


    $active = null;

    foreach ($hrefsTag as $i => $ch) {
        curl_multi_remove_handle($mh, $conn[$i]);
        curl_close($conn[$i]);

    }
    curl_multi_close($mh);

    return $pmlj;

}


/**是否找到关键词,ture 表示没有找到关键词
 * @param $html
 * @return bool
 */
function is_not_found_keyword_page($html)
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xPath = new DOMXPath($dom);
    $elements = $xPath->query('//*[@id="container"]/div[2]/div[\'nors\']/p/text()[1]');

    if (count($elements)) {
        return true;
    }
    return false;
}


/**保存查询结果
 * @param $keyword
 * @param $urls
 * @param $path
 * @param $file_name
 */
function save_result($keyword, $urls, $path, $file_name)
{
    $datas = json_encode(['keyword' => $keyword, 'urls' => $urls], JSON_UNESCAPED_UNICODE);
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    $path = $path . $file_name;
    file_put_contents($path, $datas, FILE_APPEND);
}


/**
 * 获取http 的 user_agent
 */
function get_user_agent()
{
    $ua_list = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36',
    ];
    return $ua_list[mt_rand(0, count($ua_list) - 1)];
}