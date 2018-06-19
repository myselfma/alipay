<?php
namespace App\Services;
class AlipayMobilePay
{
    protected $appId;
    protected $charset;
    protected $returnUrl;
    protected $notifyUrl;
    //私钥值
    protected $rsaPrivateKey;
    protected $totalFee;
    protected $outTradeNo;
    protected $tradeNo;
    protected $orderName;
    protected $refundAmount;

    public function __construct()
    {
        $this->charset = 'utf8';
        $this->alipayPublicKey = env('ALIPAY_PUBLIC_KEY');
    }
    public function setAppid($appid)
    {
        $this->appId = $appid;
    }
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;
    }
    public function setNotifyUrl($notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;
    }
    public function setRsaPrivateKey($rsaPrivateKey)
    {
        $this->rsaPrivateKey = $rsaPrivateKey;
    }
    public function setTotalFee($payAmount)
    {
        $this->totalFee = $payAmount;
    }
    public function setOutTradeNo($outTradeNo)
    {
        $this->outTradeNo = $outTradeNo;
    }
    public function setOrderName($orderName)
    {
        $this->orderName = $orderName;
    }
    public function setRefundAmount($refundAmount)
    {
        $this->refundAmount = $refundAmount;
    }
    public function setTradeNo($tradeNo)
    {
        $this->tradeNo = $tradeNo;
    }
    /**
     * 发起订单
     * @return array
     */
    public function doPay()
    {
        //请求参数
        $requestConfigs = array(
            'out_trade_no'=>$this->outTradeNo,
            'product_code'=>'QUICK_WAP_WAY',
            'total_amount'=>$this->totalFee, //单位 元
            'subject'=>$this->orderName,  //订单标题
        );
        $commonConfigs = array(
            //公共参数
            'app_id' => $this->appId,
            'method' => 'alipay.trade.wap.pay',             //接口名称
            'format' => 'JSON',
            'return_url' => $this->returnUrl,
            'charset'=>$this->charset,
            'sign_type'=>'RSA2',
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'notify_url' => $this->notifyUrl,
            'biz_content'=>json_encode($requestConfigs),
        );

        $commonConfigs["sign"] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);

        return $this->buildRequest($commonConfigs); //支付宝
        return http_build_query($commonConfigs);
//        return $this->buildRequestForm($commonConfigs);
    }
    
    protected function buildRequest($params) {
        $returnUrl = 'https://openapi.alipay.com/gateway.do?';
        foreach ($params as $k => $v){
            $v = $this->characet($v, $this->charset);
            $returnUrl .= '&'.$k.'='.$v;
        }
        return $returnUrl;
    }


    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @return 提交表单HTML文本
     */
    protected function buildRequestForm($para_temp) {
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='https://openapi.alipay.com/gateway.do?charset=".$this->charset."' method='POST'>";
        while (list ($key, $val) = each ($para_temp)) {
            if (false === $this->checkEmpty($val)) {
                $val = str_replace("'","&apos;",$val);
                $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }
    /**
     * 退款
     * @return array
     */
    public function doRefund()
    {
        //请求参数
        $requestConfigs = array(
            'trade_no'=>$this->tradeNo,
            'out_trade_no'=>$this->outTradeNo,
            'refund_amount'=>$this->refundAmount,
        );
        $commonConfigs = array(
            //公共参数
            'app_id' => $this->appId,
            'method' => 'alipay.trade.refund',             //接口名称
            'format' => 'JSON',
            'charset'=>$this->charset,
            'sign_type'=>'RSA2',
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'biz_content'=>json_encode($requestConfigs),
        );
        Log::Info('退款参数',['请求参数'=>$requestConfigs,'公共参数'=>$commonConfigs]);
        $commonConfigs["sign"] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);
        $result = $this->curlPost('https://openapi.alipay.com/gateway.do',$commonConfigs);
        $resultArr = json_decode($result,true);
        if(empty($resultArr)){
            $result = iconv('GBK','UTF-8//IGNORE',$result);
            return json_decode($result,true);
        }
        return $resultArr;
    }



    public function generateSign($params, $signType = "RSA") {
        return $this->sign($this->getSignContent($params), $signType);
    }


    protected function sign($data, $signType = "RSA") {
        $priKey=$this->rsaPrivateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256); //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }
    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }
    public function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }
    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
    public function rsaCheck($params) {
        $sign = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign_type']);
        unset($params['sign']);
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }


    function verify($data, $sign, $signType = 'RSA') {
        $pubKey= $this->alipayPublicKey;
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
//        if(!$this->checkEmpty($this->alipayPublicKey)) {
//            //释放资源
//            openssl_free_key($res);
//        }
        return $result;
    }
}