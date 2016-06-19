<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/17
 * Time: 23:56
 */
namespace Home\Controller;
use Think\Controller;

class UserController extends Controller
{
    public function login(){
        $this->display();
    }

    public function register(){
//        $user = D("user");
        $user = new \Model\UserModel();
        if(!empty($_POST)){
            $info = $user -> create();
            if($info){
                if(strpos(',',$info['user_hooby'])){
                    $info['user_hooby'] = implode(',',$info['user_hooby']);
                }
                $result = $user -> add($info);
                if($result){
                    $this -> redirect("Index/index",array(),2,"註冊成功");
                }
            }else{
                $this -> assign("errorInfo",$user -> getError());
            }
//            if(strpos(",",$_POST['user_hobby'])){
//                $_POST['user_hobby'] = implode(",",$_POST['user_hobby']);
//            }
            $result = $user -> add($_POST);
//            dump($result);
        }
            $this->display();
    }
}