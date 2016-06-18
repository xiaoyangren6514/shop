<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/18
 * Time: 0:19
 */

namespace Home\Controller;


use Think\Controller;

class GoodsController extends Controller
{

    public function showList(){
        $this->display();
    }

    public function detail(){
        $this->display();
    }

}