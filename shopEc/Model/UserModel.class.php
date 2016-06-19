<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/18
 * Time: 23:16
 */

namespace Model;


use Think\Model;

class UserModel extends Model{

    // 是否批处理验证
    protected $patchValidate    =   true;

    // 自动验证定义
    protected $_validate        =   array(
        //array(验证字段1,验证规则,错误提示,[验证条件,附加规则,验证时间])
        array("username","require","用戶名不能为空"),
        array("password","require","密码不能为空"),

        array("password2","require","密码确认不能为空"),
        array("password2","password","两次密码不一致",0,"confirm"),

        array("user_email","email","邮箱输入不合法",2),

        array("user_qq","number","qq号码只能输入数字",2),
        array("user_qq","5,12","qq号码只能输入数字",2,"length"),

        array("user_tel","number","手机号码只能为数字",2),
        array("user_tel","11","手机号码非法",2,"length"),

        array("user_sex","1,3","请选择性别",2,"between"),

        array("user_xueli","2,5","请选择学历",2,"between"),

        array("user_hobby","check_hobby","请选择两个以上爱好",1,"callback")
    );

    function check_hobby($args){
        if(count($args)<2){
            return false;
        }
        return true;
    }


}