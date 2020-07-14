<?php

/**
 * 获取user_agent
 */
function get_user_agent()
{
    $ua_list = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36',
    ];
    return $ua_list[mt_rand(0, count($ua_list) - 1)];
}

/**
 * 获取获取百度cookie,以后可以尝试从文件里面读取cookie,这样你更新cookie的时候,只要上传那个文件就可以.
 */

function get_baidu_cookies()
{
    return 'PSTM=1593089253; BIDUPSID=EABF07A76F880FAECFA1E25FFA854587; MSA_WH=1680_939; H_WISE_SIDS=148077_150112_150657_147345_150075_147090_150087_148194_148867_147685_148713_147279_150037_150165_149532_150154_148754_147888_148238_148524_127969_150576_149907_146550_150562_149719_150345_146732_138425_149557_149761_131423_114552_147528_147913_107318_146848_150629_148185_147717_149251_150953_150781_147989_144966_149280_148660_150340_148425_148751_147547_146054_148869_150377_110085; BD_UPN=123253; BDORZ=B490B5EBF6F3CD402E515D22BCDA1598; delPer=0; BD_CK_SAM=1; BDRCVFR[feWj1Vr5u3D]=mk3SLVN4HKm; sug=3; sugstore=0; ORIGIN=0; bdime=0; BAIDUID=9761D4561ED15171D37051713B2CF1E5:SL=0:NR=10:FG=1; BD_HOME=1; COOKIE_SESSION=1663_3_9_4_0_56_1_1_6_8_2_13_0_0_0_0_0_1594618728_1594632779%7C9%230_1_1594618364%7C1; PSINO=7; H_PS_645EC=20bc5Mb6Jhc%2FunEA30Ih3QGVrmg9nn5US0TCTHb35XuDCQDR6vkff1MjTAw; H_PS_PSSID=32216_1458_31671_32141_32139_32046_32230_32145_32258_26350_31640; BDSVRTM=0';
}


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
    if ($elements && $elements[0]->nodeValue == "百度安全验证") {
        return true;
    }
    return false;


}


/**日志
 * @param $level 日志等级
 * @param $args 日志参数
 */
function spider_log($level, $args, $file = null)
{
    $path = './logs/';
    if (!file_exists($path)) {
        mkdir($path);
    }
    if (empty($file)) {
        $file = date("Y-m-d") . '_' . $level . '.log';
    }
    $path = $path . $file;

    $args_str = "";
    foreach ($args as $key => $value) {
        $args_str = $args_str . $key . ": " . $value . ", ";
    }
    $log = date("Y-m-d H:i:s") . ", " . $args_str . "\r\n";

    file_put_contents($path, $log, FILE_APPEND);

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

