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
        $this->display();
    }
}