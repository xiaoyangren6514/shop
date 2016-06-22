<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/18
 * Time: 18:10
 */

namespace Admin\Controller;


use Think\Controller;
use Think\Verify;

class ManagerController extends Controller
{

    function login(){
        if($_POST){
            $verify = new Verify();
            if($verify ->check($_POST["captcha"])){
                echo "success";
            }else{
                echo "error";
            }
        }
        $this->display();
    }

    function verifyImg(){
        $config = array(
            'fontSize'  =>  16,              // 验证码字体大小(px)
            'imageH'    =>  45,               // 验证码图片高度
            'imageW'    =>  150,               // 验证码图片宽度
            'length'    =>  4,               // 验证码位数
        );
        $verify = new Verify($config);
        $verify -> entry();
    }

}