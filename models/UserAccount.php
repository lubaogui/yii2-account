<?php
/*************************************************************************** *
 * Copyright (c) 2015 Lubanr.com All Rights Reserved
 *
 **************************************************************************/
 
namespace lubaogui\account\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use lubaogui\account\models\Bill;
use lubaogui\account\models\Freeze;
use lubaogui\account\models\UserAccountLog;
 
/**
 * @file UserAccount.php
 * @author 吕宝贵(lbaogui@lubanr.com)
 * @date 2015/11/29 11:26:09
 * @version $Revision$
 * @brief
 *
 **/

class UserAccount extends ActiveRecord 
{

    //账户类型,三种，普通账户，公司账户，银行账户,默认时普通用户账户
    const ACCOUNT_TYPE_NORMAL = 1;              //个人普通账号
    const ACCOUNT_TYPE_COMPANY = 20;            //公司类型账号，非自有公司
    const ACCOUNT_TYPE_SELFCOMPANY_PAY = 30;    //公司现金支付账号
    const ACCOUNT_TYPE_SELFCOMPANY_VOUCH = 40; //担保账号
    const ACCOUNT_TYPE_SELFCOMPANY_PROFIT = 50; //利润账号
    const ACCOUNT_TYPE_SELFCOMPANY_FEE = 60;    //公司手续费收费账号

    //支出类型

    const BALANCE_TYPE_PLUS = 1;          //收入
    const BALANCE_TYPE_MINUS = 2;         //支出
    const BALANCE_TYPE_FREEZE = 3;        //冻结
    const BALANCE_TYPE_UNFREEZE = 4;      //冻结取消
    const BALANCE_TYPE_FINISH_FREEZE = 5; //冻结关联动作完成

    /**
     * @brief 获取表名称，{{%}} 会自动将表名之前加前缀，前缀在db中定义
     *
     * @retval string 表名称  
     * @author 吕宝贵
     * @date 2015/11/29 11:48:52
    **/
    public static function tableName() {
        return '{{%user_account}}';
    }

    /**
     * @brief 自动设置 created_at和updated_at
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/29 16:19:03
    **/
    public function behaviors() {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @brief 
     *
     * @param int $uid 用户id
     * @param int $type 用户账号类型
     * @param int $currency 货币类型，默认为１, 人民币
     * @return  bool 是否创建成功 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/17 11:15:12
    **/
    public static function createAccount($uid, $type = ACCOUNT_TYPE_NORMAL, $currency = 1) {

        if ($type != self::ACCOUNT_TYPE_NORMAL) {
        
        }

        $account = new static();
        $account->uid = $uid;
        $account->currency = $currency;
        $account->type = $type;
        $account->is_enabled = 1;

        if ($account->save()) {
            return $account;
        }
        else {
            return false;
        }

    }

    /**
     * @brief 获取公司付款账号
     *
     * @return  public function 
     * @author 吕宝贵
     * @date 2016/01/07 10:57:17
     **/
    public static function getCompanyPayAccount() {
        return self::getPayAccount(self::ACCOUNT_TYPE_SELFCOMPANY_PAY);
    }

    /**
     * @brief 担保交易中间账号
     *
     * @return  public function 
     * @author 吕宝贵
     * @date 2016/01/07 11:06:18
     **/
    public static function getVouchAccount() {
        return self::getPayAccount(self::ACCOUNT_TYPE_SELFCOMPANY_VOUCH);
    }

    /**
     * @brief 担保交易中间账号
     *
     * @return  public function 
     * @author 吕宝贵
     * @date 2016/01/07 11:06:18
     **/
    public static function getProfitAccount() {
        return self::getPayAccount(self::ACCOUNT_TYPE_SELFCOMPANY_PROFIT);
    }

    /**
     * @brief 担保交易中间账号
     *
     * @return  public function 
     * @author 吕宝贵
     * @date 2016/01/07 11:06:18
     **/
    public static function getFeeAccount() {
        return self::getPayAccount(self::ACCOUNT_TYPE_SELFCOMPANY_FEE);
    }

    /**
     * @brief 担保交易中间账号
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/07 11:06:18
     **/
    protected static function getPayAccount($type = self::ACCOUNT_TYPE_NORMAL) {

        $account = self::findOne(['type'=>$type]);
        if (! $account) {
            throw new Exception('必须设置对应的账号');
        }
        return $account;

    }

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/01 16:26:45
    **/
    public function plus($money) {
        $this->balance += $money;
        return true;
    }

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/04 22:57:20
    **/
    public function minus($money) {
        if ($this->balance - $money < 0) {
            $this->addError(__METHOD__, '余额不足，无法支持操作');
            return false;
        }
        $this->balance -= $money;
        return true;
    }

    /**
     * @brief 冻结用户的金额,该操作仅操作用户账户上的金额，不填写freeze记录,freeze记录在上层逻辑上填写
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/04 23:50:06
    **/
    public function freeze($money) {
        if ($this->balance - $money < 0) {
            $this->addError(__METHOD__, '余额不足，无法支持冻结操作');
            return false;
        }
        $this->balance -= $money;
        $this->frozen_money += $money;
        return true;
    }

    /**
     * @brief 解锁冻结金额，注意，解锁是锁定的逆向操作，如果是操作完成，请使用finishFreeze
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 22:03:21
    **/
    public function unfreeze($money) {
        if ($this->frozen_money - $money < 0) {
            $this->addError(__METHOD__, '冻结金额不足，无法支持解冻操作');
            return false;
        }
        $this->balance += $money;
        $this->frozen_money -= $money;
        return true;
    }

    /**
     * @brief 完成金额冻结，从冻结余额中扣除冻结记录对应的金额
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 14:53:04
    **/
    public function finishFreeze($money) {
        if ($this->frozen_money - $money < 0) {
            $this->addError(__METHOD__, '冻结金额不足，无法支持完成冻结操作');
            return false;
        }
        $this->frozen_money -= $money;
        return true;
    }


}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
