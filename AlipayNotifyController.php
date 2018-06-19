<?php
/**
*命名空间自己修改一下啊
*这个是laravel中使用的。
*env是自定义敞亮
*/
namespace App\Http\Controllers\Api;

use App\Services\AlipaySDKService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\AlipayMobilePay;
class AlipayNotifyController extends CommonController
{
    public function setConfig(){
        $appid = env('ALIPAY_APP_ID');  //https://open.alipay.com 账户中心->密钥管理->开放平台密钥，填写添加了电脑网站支付的应用的APPID
        $returnUrl = env('ALIPAY_RETURNURL');     //付款成功后的同步回调地址
        $notifyUrl = env('ALIPAY_NOTIFYURL');     //付款成功后的异步回调地址
        $signType = 'RSA2';			//签名算法类型，支持RSA2和RSA，推荐使用RSA2
        $rsaPrivateKey= env('MERCHANT_PRIVATE_KEY');		//商户私钥，填写对应签名算法类型的私钥，如何生成密钥参考：https://docs.open.alipay.com/291/105971和https://docs.open.alipay.com/200/105310
        $aliPay = new AlipayMobilePay();
        $aliPay->setAppid($appid);
        $aliPay->setReturnUrl($returnUrl);
        $aliPay->setNotifyUrl($notifyUrl);
        $aliPay->setRsaPrivateKey($rsaPrivateKey);
        return $aliPay;
    }

    public function pay(){
        header('Content-type:text/html; Charset=utf-8');
        $aliPay = $this->setConfig();
        $outTradeNo = uniqid();     //你自己的商品订单号
        $payAmount = 0.01;          //付款金额，单位:元
        $orderName = '支付测试';    //订单标题
        $aliPay->setTotalFee($payAmount);
        $aliPay->setOutTradeNo($outTradeNo);
        $aliPay->setOrderName($orderName);
        $sHtml = $aliPay->doPay();
        Log::Info('请求',['信息'=>$sHtml,'outtradeno'=>$outTradeNo]);
        echo $sHtml;
    }

    public function alipayReturn(Request $request){
        header('Content-type:text/html; Charset=utf-8');
        $aliPay = new AlipayMobilePay();
        //验证签名
        $result = $aliPay->rsaCheck($_GET,$_GET['sign_type']);
        Log::Info('同步回调',['信息'=>$_GET]);
        Log::Info('同步回调',['结果'=>$result]);
        if($result===true){
            echo '<h1>付款成功</h1>';exit();
        }
        echo '不合法的请求';exit();
    }

    public function alipayNotify(){
        header('Content-type:text/html; Charset=utf-8');
//支付宝公钥，账户中心->密钥管理->开放平台密钥，找到添加了支付功能的应用，根据你的加密类型，查看支付宝公钥
        $aliPay = new AlipayMobilePay();
//验证签名
        $result = $aliPay->rsaCheck($_POST,$_POST['sign_type']);
        Log::Info('异步回调',['信息'=>$_POST]);
        Log::Info('异步回调',['结果'=>$result]);
        if($result===true){
            //处理你的逻辑，例如获取订单号$_POST['out_trade_no']，订单金额$_POST['total_amount']等
            //程序执行完后必须打印输出“success”（不包含引号）。如果商户反馈给支付宝的字符不是success这7个字符，支付宝服务器会不断重发通知，直到超过24小时22分钟。一般情况下，25小时以内完成8次通知（通知的间隔频率一般是：4m,10m,10m,1h,2h,6h,15h）；
            echo 'success';exit();
        }
        echo 'error';exit();
    }

    public function refund(Request $request)
    {
        header('Content-type:text/html; Charset=utf-8'); 
        $outTradeNo = $request->route('outtradeno');
        $tradeNo = $request->route('tradeno');
        $refundAmount = 0.01;
        $aliPay = $this->setConfig();
        $aliPay->setRefundAmount($refundAmount);
        $aliPay->setTradeNo($tradeNo);
        $aliPay->setOutTradeNo($outTradeNo);
        $result = $aliPay->doRefund();
        $result = $result['alipay_trade_refund_response'];
        if($result['code'] && $result['code']=='10000'){
            echo '<h1>退款成功</h1>';
        }else{
            echo $result['msg'].' : '.$result['sub_msg'];
        }
    }

}