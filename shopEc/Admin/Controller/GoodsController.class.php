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

    function add(){
        $goods = D("goods");
        if(!empty($_POST)){
            $result = $goods -> add($_POST);
            if($result){
                $this -> redirect('showList',array(),2,"数据添加成功");
            }else{
                $this -> redirect('add',array(),2,"数据添加失败");
            }
        }else{
            $this -> display();
        }
    }

    function showList(){
        $goods = D("goods");
        $info = $goods -> order("goods_id desc")->select();
//        dump($info);
        $this -> assign("info",$info);
        $this -> display();
    }

    function update($goods_id)
    {
        $goods = D("goods");
        if (!empty($_POST)) {
            $result = $goods -> save($_POST);
            if($result){
                $this -> redirect("showList",array(),2,"修改成功");
            }else{
                $this -> redirect("update",array("goods_id"=>$goods_id),2,"修改失败");
            }
        } else {
            $info = $goods->where("goods_id = " . $goods_id)->find();
            if ($info) {
                $this->assign("info", $info);
            }
            $this->display();
        }
    }

    function delete($goods_id){
        $goods = D("goods");
        $result = $goods -> delete($goods_id);
        if($result){
            $this -> redirect("showList",array(),2,"刪除成功");
        }else{
            echo "刪除失敗";
        }
    }

    public function add1(){

        $goods = D("goods");
//        $arr = array(
//            'goods_name' => 'iphone8s',
//            'goods_price' => 1000
//        );
//        $res = $goods -> add($arr);
        $goods -> goods_name = 'sanxing';
        $goods -> goods_price = 9000;
        $res = $goods->add();
        dump($res);
        $this->display();
    }

    public function showList1(){
        $goods = new \Model\GoodsModel();
        $result = $goods->select();
//        dump($result);
        $result = $goods -> select("18,17,29");
        $goods -> where(" goods_name like '诺%' and goods_price > '100'");
//        $goods -> limit(1);
        $result = $goods -> select();

        $goods -> order('goods_price desc');
        $result = $goods ->select();

//        $goods -> group('goods_brand_id');
//        $goods -> field('goods_brand_id,count(*)');
//        $result = $goods -> select();
//        dump($result);

        $this -> assign("info",$result);
        $this->display();
    }

    public function showList2(){
//        $goods = new \Model\GoodsModel();
//        var_dump($goods);
        $obj = D("user");
        dump($obj);
        $this->display();
    }

    function update1(){
        $goods = D("goods");
//        $goods -> goods_id = 156;
        $goods -> goods_name = "wangcai";
        $goods ->where("goods_id >150 and goods_id < 160");
        $result = $goods ->save();
        dump($result);
        $this->display();
    }

}