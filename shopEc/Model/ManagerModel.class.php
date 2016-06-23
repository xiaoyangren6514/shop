<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/23
 * Time: 8:15
 */

namespace Model;


use Think\Model;

class ManagerModel extends Model
{

    function checkNamePwd($name,$pwd){
        $result = $this -> where(" mg_name = '$name' and mg_pwd = '$pwd' ")->find();
        return $result;
    }

}