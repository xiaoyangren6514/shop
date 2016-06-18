<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/18
 * Time: 18:09
 */

namespace Admin\Controller;


use Think\Controller;

class IndexController extends Controller
{
    function index()
    {
//        dump(get_defined_constants(true));
        $this->display();
    }

    function left(){
        $this->display();
    }

    function right(){
        $this->display();
    }

    function head()
    {
        // 设置不显示trace跟踪信息
        C('SHOW_PAGE_TRACE', false);
        $this->display();
    }

}