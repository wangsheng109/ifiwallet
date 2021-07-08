<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class One extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->helper('url_helper');
                $this->load->model('One_coin_model');
                $this->rpc_url = "http://localhost:".$this->config->item("onePort")."/rpc";
                $this->coin_name = "one";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('one_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')."\r\n");
                }
        }
        
        public function info() {
            $this->unlockAccount();
            $out = $this->get_info();
            var_dump($out);
        }
        
        private function get_info() {
            $out = $this->call("info");
            return $out;
        }
        // block numbers to time stamp
        private function b2t($blocks) {
            $info = $this->get_info();
            $current_blocks = $info['head_block_num'];
            $thetime = time() - ($current_blocks-$blocks)/3;
            return intval($thetime);
        }
        
	public function newAccount() {
            $accounts_count = $this->One_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $memo = time()+$i*1000000+rand(0,100000);
                $data['address'] = $memo;
                $data['account'] = $this->config->item('oneAccount');
                $data['create_time'] = $data['update_time'] = time();
                $res = $this->One_coin_model->create_account($data);
            }
            echo "成功创建了 ".$i." 个ONE新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            //get next_start_squence and account_name for gxb coin
            $chain_data = $this->One_coin_model->get_chain_data($this->coin_name);
            $next_start = $chain_data->next_start_sequence;
            $this->unlockAccount();
            $get_payments_params = [
                $this->config->item('oneAccount'),
                intval($next_start),
                100,
                intval($next_start)+100
            ];
            $payments = $this->call("get_relative_account_history",$get_payments_params);
            
            if(!isset($payments[0]['memo'])){
                var_dump($payments);
                exit("List payments error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            foreach($payments as $item) {
                if(!isset($item['op']['op'][1]['to'])){
                    echo "不是转账交易\r\n";
                    continue;
                }
                if($item['op']['op'][1]['to']!=$this->config->item('oneAccountId')){
                    echo "to : ".$item['op']['op'][1]['to']."\r\n";
                    echo "不是充值账户号\r\n";
                    continue;
                }
                //retrieve info such as balance from cold wallet by the address(memo) and coin_id
                $cold_wallet_array = $this->One_coin_model->get_cold_wallet(['address'=>$item['memo']],true);
                if(!isset($cold_wallet_array[0])) {
                    echo "memo : ".$item['memo']."\r\n";
                    echo "地址不在冷钱包数据库里面\r\n";
                    continue;
                }
                $cold_wallet = $cold_wallet_array[0];
                $value = $item['op']['op'][1]['amount']['amount']/100000;
                $asset = $item['op']['op'][1]['amount']['asset_id'];
                if($asset != "1.3.0"){
                    echo "资产类别不是one,而是".$asset."\r\n";
                    continue;
                }
                $log_db_data['uid'] = $cold_wallet['uid'];
                $log_db_data['address'] = $cold_wallet['address'];
                $log_db_data['amount'] = $value;
                $log_db_data['tx_hash'] =  $item['op']['block_num'].'-'.$item['op']['trx_in_block'];
                $check_duplicate = $this->One_coin_model->check_deposit_log_duplicate($log_db_data['tx_hash'],$log_db_data['address']);
                if(intval($check_duplicate)>0){
                    echo "交易hash-地址对重复, 交易hash: ".$log_db_data['tx_hash'].",地址/memo:".$log_db_data['address']."\r\n";
                    continue;
                }
                $log_db_data['transfer_time'] = $this->b2t(intval($item['op']['block_num']));
                $log_db_data['confirmations'] = 12;
                $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                $db_amount = floatval($cold_wallet['balance']);
                $db_amount = calculate($db_amount,$value,"bcadd");
                $this->One_coin_model->insert_db("coin_deposit_log",$log_db_data);
                $cold_wallet_db['balance'] = $db_amount;
                $this->One_coin_model->update_cold_wallet($cold_wallet_db,$cold_wallet['id']);
                $complete +=1;
            }
            $new_next_start = intval(count($payments))+$next_start;
            $this->One_coin_model->update_chain_data(["next_start_sequence"=>$new_next_start],$this->coin_name); 
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n";            
        }        
        
        
        private function get_balance($account) {
            $res = $this->call("list_account_balances",[$account]);
            $balance = intval($res[0]['amount'])/100000;
            return $balance;
        }
        
       
        // Withdraw money to user
        public function withdraw() {
            $complete = 0;
            $res = $this->One_coin_model->get_withdraw_log(200);
            foreach($res as $item) {
                if($this->get_balance($this->config->item('oneAccount'))<$item['coin_actual_amount']+1){
                    echo "现有余额: ".$this->get_balance($this->config->item('oneAccount'))."\r\n";
                    echo "至少要支付: ".$item['coin_actual_amount']."\r\n";
                    exit("热钱包余额不足!\r\n");
                }
                $id = $item['id'];
                $memo = $item['memo'];
                $result = $this->gen_tx($item['coin_actual_amount'],$this->config->item('oneAccount'),$item['coin_address'],$memo);
                if(!isset($result['operations'][0][1]['memo']['nonce'])) {
                    var_dump($result);
                    $data['tx_hash'] = $result['ref_block_prefix']."-".$result['ref_block_num'];
                } else {
                    $data['tx_hash'] = $result['ref_block_prefix']."-".$result['ref_block_num']."-".$result['operations'][0][1]['memo']['nonce'];

                }
                $data['status'] = 280;
                $data['update_time'] = time();
                $complete += 1;
                $this->One_coin_model->update_withdraw_log($data,$id);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        private function gen_tx($value,$from,$to,$memo) {
            $param = [
                $from,
                $to,
                sprintf("%.5f",floatval($value)),
                "ONE",
                $memo,
                true
            ];
            $this->unlockAccount();
            $odata = [
            "jsonrpc" => "2.0",
            "method"  => "transfer",
            "params"  => $param,
            'id' =>time()
            ];
         $out = $this->call("transfer",$param);
         return $out;
        }
        
        private function unlockAccount() {
            return $this->call("unlock",[$this->config->item('onePWD')]);
        }
        
        private function importKey($privateKey) {
            $odata = [
                "jsonrpc"=> "2.0",
                "method"=>"import_key",
                "params"=>["James_BXB".time(),$privateKey,true],
                'id' =>time()
                ];
            $out = commit_curl("http://localhost:".$this->config->item("gxbPort")."/rpc",false,1,$odata);
            return $out;
        }
        
}


