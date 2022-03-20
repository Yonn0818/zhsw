<?php
// +----------------------------------------------------------------------
// | 文件: index.php
// +----------------------------------------------------------------------
// | 功能: 提供todo api接口
// +----------------------------------------------------------------------
// | 时间: 2021-11-15 16:20
// +----------------------------------------------------------------------
// | 作者: rangangwei<gangweiran@tencent.com>
// +----------------------------------------------------------------------

namespace app\controller;

use Error;
use Exception;
use app\model\Counters;
use think\response\Html;
use think\response\Json;
use think\facade\Log;

class Index
{
    public function test(){
        $data = [
            'code'=>'200ok',
            'msg'=>'Hello World'
        ];
        return json_encode($data);
    }

    //https
    public function announcement_curl($url,$data){
        $header= array('Expect:');  
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_POST, true);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);  
        $response = curl_exec($ch);  
        if($error=curl_error($ch)){  
            return $error;  
        }  
        curl_close($ch);  
        return $response;  
    }

    //请求获取access_token
    public function get_access_token(){
        $url = 'https://aip.baidubce.com/oauth/2.0/token';
        $post_data['grant_type']       = 'client_credentials';
        $post_data['client_id']      = 'M8Nz4nWf38PI60Vu38xHiOce';
        $post_data['client_secret'] = '3AlGujovNSSDfpCn5b4qXdKNuKjvZDF3';
        $o = "";
        foreach ( $post_data as $k => $v ) 
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);
        $res = $this->announcement_curl($url,$post_data);
        $res = json_decode($res,true);
        $res['timeout'] = time()+$res['expires_in'];
        file_put_contents('./access_token.token',json_encode($res));
        return $res;
    }

    //检查access_token
    public function get_token(){
        $info = json_decode(file_get_contents('./access_token.token'),true);
        if($info['timeout']<time()){
            $info = $this->get_access_token();
        }

        $token = $info['access_token'];
        log_message('info', 'access_token:'.print_r($token,true).'--line:'.__LINE__);

        echo $token;
    }

    public function send_pic(){
        $result = ['code'=>'400','msg'=>'识别失败'];
        $data = $_POST;
        log_message('info', '接收参数:'.print_r($data,true).'--line:'.__LINE__);
        log_message('info', '接收文件:'.print_r($_FILES,true).'--line:'.__LINE__);
        
        $info = json_decode(file_get_contents('./access_token.token'),true);
        if(empty($info) || $info['timeout']<time()){
            $info = $this->get_access_token();
        }

        $token = $info['access_token'];
        log_message('info', 'access_token:'.print_r($token,true).'--line:'.__LINE__);

        $type = $_GET['type'];
        $url_list = [
            'animal'=>'https://aip.baidubce.com/rest/2.0/image-classify/v1/animal',
            'plant'=>'https://aip.baidubce.com/rest/2.0/image-classify/v1/plant',
            'ingredient'=>'https://aip.baidubce.com/rest/2.0/image-classify/v1/classify/ingredient',
            'advanced_general'=>'https://aip.baidubce.com/rest/2.0/image-classify/v2/advanced_general',
        ];
        $url = $url_list[$type].'?access_token=' . $token;
        $bodys = array(
            'image'=>base64_encode(file_get_contents($_FILES['img']['tmp_name'])),
            'top_num'=>3,
            'baike_num'=>3
        );

        $res = json_decode($this->announcement_curl($url,$bodys),true);
        log_message('info', '识别结果:'.print_r($res,true).'--line:'.__LINE__);

        //判断是否识别成功
        if(isset($res['error_code']) && $res['error_code']!=''){
            $result = ['code'=>'500','msg'=>'未识别成功,请检查图片是否正常'];
            echo json_encode($result);exit;
        }
        $is_ok = 0;
        if(count($res['result'])>0){
            $is_ok = 1;
            foreach ($res['result'] as $key => $value) {
                // if($value['score']>0.3){
                    $res['result'][$key]['score'] = sprintf('%.2f',$res['result'][$key]['score']*100);
                // }
            }
        }
        

        $result['code'] = '200ok';
        $result['msg'] = '识别成功';
        $result['is_ok'] = $is_ok;
        // $result['img'] = 'https://yonnn.top'.$path;
        $result['data'] = $res;
        return json_encode($result);
    }
}
