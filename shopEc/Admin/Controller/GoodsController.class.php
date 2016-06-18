<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/18
 * Time: 20:05
 */

namespace Admin\Controller;


use Model\GoodsModel;
use Think\Controller;

class GoodsController extends Controller
{

    public function add(){
        $this->display();
    }

    public function showList(){
        $goods = new \Model\GoodsModel();
        var_dump($goods);
        $this->display();
    }

    function update(){
        $this->display();
    }

}