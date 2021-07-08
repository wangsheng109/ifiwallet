<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wallet extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Wallet_model');
        }

        public function user_deposit()
        {
            $deposit_log = $this->Wallet_model->get_deposit_logs(array('status'=>200));
            if(empty($deposit_log)){
                echo "\r\n There is no withdraw log \r\n";
                exit;
            }
            $pre_amount = array();
            foreach($deposit_log as $log){
                $key = $log['coin_id'].'-'.$log['user_id'];
                if(empty($pre_amount[$key])){
                    $pre_amount[$key]=floatval($log['amount']);
                } else {
                    $pre_amount[$key]+=floatval($log['amount']);
                }
                $this->Wallet_model->update_deposit_logs(array('status'=>300),array('id'=>$log['id']));
            }
            if(empty($pre_amount)){
                echo "\r\n There is no withdraw log \r\n";
                exit;
            }
            foreach($pre_amount as $k => $v){
                $ka = explode('-',$k);
                if(empty($ka[0])||empty($ka[1])){
                    echo "\r\n Inter problem \r\n";
                    exit;
                }
                $coin_id=$ka[0];
                $user_id=$ka[1];
                $where = array(
                    'coin_id'   =>  $coin_id,
                    'user_id'   =>  $user_id
                );
                
                $this->Wallet_model->update_user_wallet($v,$where);
            //    $this->update_class($user_id);
                
            }
            echo "\r\n Update user wallet successfully \r\n";
        }

        public function user_withdraw()
        {
            $withdraw_log = $this->wallet_model->get_withdraw_logs(array('status'=>200));
            if(empty($withdraw_log)){
                echo "\r\n There is no withdraw log \r\n";
                exit;
            }
            $pre_amount = array();
            foreach($withdraw_log as $log){
                $key = $log['coin_id'].'-'.$log['user_id'];
                if(empty($pre_amount[$key])){
                    $pre_amount[$key]=floatval($log['amount']);
                } else {
                    $pre_amount[$key]+=floatval($log['amount']);
                }
                $this->wallet_model->update_withdraw_logs(array('status'=>300),array('id'=>$log['id']));
            }
            if(empty($pre_amount)){
                echo "\r\n There is no withdraw log \r\n";
                exit;
            }
            foreach($pre_amount as $k => $v){
                $ka = explode('-',$k);
                if(empty($ka[0])||empty($ka[1])){
                    echo "\r\n Inte problem \r\n";
                }
                $coin_id=$ka[0];
                $user_id=$ka[1];
                $where = array(
                    'coin_id'   =>  $coin_id,
                    'user_id'   =>  $user_id
                );
                
                $this->wallet_model->frozen_coin($v,$where);
                $this->update_class($user_id);
            }
            echo "\r\n Update user wallet successfully \r\n";
        }
        
        
}


