<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Eos extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Eos_coin_model');
            //    $this->base_url = "http://127.0.0.1:8888/v1/";
                $this->base_url = "http://120.77.176.182:8888/v1/";
            //    $this->base_url = "https://api.jungle.alohaeos.com/";
                $this->wallet_url = "http://127.0.0.1:3000/v1/";
                $this->coin_id = $this->config->item('eos_coin_id');
        }
        
        public function info() {
            $out = $this->get_info();
            var_dump(json_decode($out,true));
        }
        
        private function get_info() {
            $out = commit_curl($this->base_url."chain/get_info");
            return $out;
        }
        
        public function get_key_accounts() {
            $odata = [
                "public_key" => "EOS6Dg5Joa7UzdbyiVuB6g2Rsvt3N8wM9R7q5zYWUBLagBVM8EToz",
            ];
            $out = $this->callApi("https://eos.greymass.com/v1/history/get_key_accounts",$odata);
            var_dump($out);
        }
        
	    public function newAccount() {
            $accounts_count = $this->Eos_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $memo = time()+$i*1000000+rand(0,100000);
                $data['address'] = $memo;
                $data['account'] = $this->config->item('eosAccount');
                $data['create_time'] = $data['update_time'] = time();
                $res = $this->Eos_coin_model->create_account($data);
            }
            echo "成功创建了 ".$i." 个EOS新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['coin_id'] = $this->coin_id;
            //get next_start_squence and account_name for gxb coin
            $coin_arr = $this->Eos_coin_model->get_coins(array('id'=>$this->coin_id));
            if(empty($coin_arr[0])){
                exit("\r\n coin does not exists \r\n");
            }
            $coin_info = $coin_arr[0];
            $next_start = $coin_info['next_start'];
            $account = $this->config->item('eosAccount');
            $get_payments_params = [
                "account_name" => iconv(mb_detect_encoding($account, mb_detect_order(), true), "UTF-8", $account),
                "pos" => intval($next_start),
                "offset" => 5
            ];
            $list_payments = $this->callApi($this->base_url."history/get_actions",$get_payments_params);
            if(!isset($list_payments['actions'])){
                var_dump($get_payments_params);
                var_dump($list_payments);
                $new_next_start = intval($next_start);
                $this->Eos_coin_model->update_db('coins',["next_start"=>$new_next_start],['id'  =>  $this->coin_id]); 
                echo "next start sequence : ".$next_start." \r\n";
                exit("List payments error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            $payments = $list_payments['actions'];
            $i = 0;
            foreach($payments as $item) {
                $i++;
                if($item['action_trace']['act']['name'] != "transfer"){
                    continue;
                }
                if($item['action_trace']['act']['data']['to']!=$account){
                    echo "to : ".$item['action_trace']['act']['data']['to']."\r\n";
                    echo "不是充值账户号\r\n";
                    continue;
                }
                $quantity = $item['action_trace']['act']['data']['quantity'];
                $q_array = explode(" ", $quantity);
                if($q_array[1]!="EOS"){
                    echo "充值token不是EOS，而是：".$q_array[1]."\r\n";
                    continue;
                }
                $memo = $item['action_trace']['act']['data']['memo'];
                //retrieve info such as balance from cold wallet by the address(memo) and coin_id
                $cold_wallet_array = $this->Eos_coin_model->get_cold_wallet(['address'=>$memo],true);
                if(!isset($cold_wallet_array[0])) {
                    echo "memo : ".$memo."\r\n";
                    echo "地址不在冷钱包数据库里面\r\n";
                    continue;
                }
                $is_duplicate = $this->Eos_coin_model->check_deposit_log_duplicate($item['action_trace']['trx_id'],$memo);
                if(intval($is_duplicate)>0) {
                    echo "已经存在该交易：".$item['action_trace']['trx_id']."\r\n";
                    continue;
                }
                $cold_wallet = $cold_wallet_array[0];
                $value = $q_array[0];
                $log_db_data['user_id'] = $cold_wallet['user_id'];
                $log_db_data['chain_id'] = $coin_info['chain_id'];
                $log_db_data['address'] = $cold_wallet['address'];
                $log_db_data['amount'] = $value;
                $log_db_data['tx_hash'] =  $item['action_trace']['trx_id'];
                $log_db_data['tx_timestamp'] = strtotime($item['block_time']);
                $log_db_data['status'] = 100;
                $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                $this->Eos_coin_model->insert_db("coin_deposit_log",$log_db_data);
            }
            $new_next_start = $i+intval($next_start);
            $this->Eos_coin_model->update_db('coins',["next_start"=>$new_next_start],['id'  =>  $this->coin_id]); 
            echo "Deposit successfully:".date('Y-m-d H:i:s')."\r\n";            
        }
        
        public function depositConfirm() {
            $deposits = $this->Eos_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_res = $this->get_tx($item['tx_hash']);
                if (empty($tx_res)||!isset($tx_res['last_irreversible_block'])||!isset($tx_res['block_num'])) {
                    var_dump($tx_res);
                    echo $item['tx_hash']." 交易等待广播中...\r\n";
                    continue;
                }
                if (intval($tx_res['last_irreversible_block'])>intval($tx_res['block_num'])+5) {
                    $data['status'] = 200;
                    $this->Eos_coin_model->update_deposit_log($data,$item['id']);
                    echo $item['tx_hash']." 交易确认数更新成功\r\n";
                } else {
                    var_dump($tx_res);
                    echo $item['tx_hash']." 交易已经广播，但未达到确认数\r\n";
                }
            }
        }
        
        // Withdraw money to user
        public function withdraw() {
            $complete = 0;
            $res = $this->Eos_coin_model->get_withdraw_log(200);
            foreach($res as $tx) {
                $id = $tx['id'];
                $memo = $tx['memo'];
                //地址格式不正确
                if(strlen($tx['coin_address'])!=12){
                    echo "地址: ".$tx['coin_address']." 格式有误";
                    continue;
                }
                $tx_gen_res = $this->gen_tx($tx['coin_actual_amount'],$this->config->item('eosAccount'),$tx['coin_address'],$memo);
                if(intval($tx_gen_res)==0){
                    echo "获取abi-bin码失败\r\n!";
                    continue;
                }
                if(intval($tx_gen_res)==2){
                    echo "获取最大区块的timestamp失败\r\n!";
                    continue;
                }
                if(empty($tx_gen_res['transaction_id'])) {
                    $tx_param['amount'] = $tx['coin_actual_amount'];
                    $tx_param['from'] = $this->config->item('eosAccount');
                    $tx_param['to'] = $tx['coin_address'];
                    $tx_param['memo'] = $memo;
                    var_dump($tx_param);
                    var_dump($tx_gen_res);
                    $data['tx_hash'] = "";
                } else {
                    $data['tx_hash'] = $tx_gen_res['transaction_id'];
                    $complete += 1;
                }
                $data['status'] = 210;
                $data['update_time'] = time();
                
                echo "tx hash : ".$tx_gen_res['transaction_id'].",the time is :".date('Y-m-d H:i:s')."\r\n";
                $this->Eos_coin_model->update_withdraw_log($data,$id);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Eos_coin_model->get_withdraw_log(210);
            $complete = 0;
            foreach($res as $tx) {
                $id = $tx['id'];
                $tx_res = $this->get_tx($tx['tx_hash']);
                if(!isset($tx_res['block_num'])||!isset($tx_res['last_irreversible_block'])) {
                    var_dump($tx_res);
                    continue;
//                    $data['status'] = 400;
//                    $data['update_time'] = time();
//                    $this->Eos_coin_model->update_withdraw_log($data,$id);
                } else if(intval($tx_res['last_irreversible_block'])>intval($tx_res['block_num'])) {
                    $data['status'] = 280;
                    $data['update_time'] = time();
                    $complete += 1;
                    $this->Eos_coin_model->update_withdraw_log($data,$id);
                } else {
                    echo "未达到不可回退区块:".$tx['tx_hash']."\r\n";
                    continue;
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function test_tx($v) {
            
            $res = $this->gen_tx($v,"jamesnewacct","jameswalston","test memo");
            var_dump($res);
            
        }
        
        public function test_get_tx() {
            $res = $this->get_tx($_GET['txid']);
            var_dump($res);
        }
        
        private function gen_tx($value,$from,$to,$memo) {
           $out = $this->unlockAccount();
           $from = $from;
           $value = sprintf("%.4f",floatval($value));
           $quantity = $value." EOS";
           $bin = $this->get_abi_bin($from, $to, $quantity, $memo);
           if(intval($bin)==0){
               return 0;
           }
           $info = $this->get_info();
           $latest_block_number = json_decode($info,true)['head_block_num'];
           $chain_id = json_decode($info,true)['chain_id'];
           $latest_block = $this->get_block($latest_block_number);
           if(!isset($latest_block['timestamp'])){
               var_dump($latest_block);
               return 2;
           }
           $timestamp = $latest_block['timestamp'];
           $ref_block_number = $latest_block['block_num'];
           $ref_block_prefix = $latest_block['ref_block_prefix'];
           $available_keys = $this->keyList();
           $authorization[0]=[
             "actor"        =>  $from,
             "permission"   => "active"
           ];
           $actions[0] = [
             "account"           => "eosio.token",
             "authorization"     => $authorization,
             "data"            =>   $bin,
             "name"            => "transfer"
           ];
           $context_free_actions = array();
           $context_free_data = array();
           $signatures = array();
           date_default_timezone_set('UTC');
        //   $timestamp = time()-178000;//可能是系统时间造成的误差，保留此处
           $timestamp = time()+30;
           $day = date('Y-m-d',$timestamp);
           $time = date('H:i:s',$timestamp);
           $expiration = $day."T".$time;
           $transaction = [
               "actions"                  =>  $actions,
               "context_free_actions"    =>  $context_free_actions,
               "context_free_data"      =>  $context_free_data,
               "signatures"             =>  $signatures,
               "delay_sec"              =>  0,
               "expiration"             =>  $expiration,
               "max_kcpu_usage"         =>  0,
               "max_net_usage_words"    =>  0,
               "ref_block_num"          =>  $ref_block_number,
               "ref_block_prefix"       =>  $ref_block_prefix
           ];
           $post_required_keys = [
             "available_keys"   =>  $available_keys,
               "transaction"    =>  $transaction
           ];
           $transaction2 = [
               "actions"                =>  $actions,
               "signatures"             =>  $signatures,
               "expiration"             =>  $expiration,
               "ref_block_num"          =>  $ref_block_number,
               "ref_block_prefix"       =>  $ref_block_prefix
           ];
           $keys_out = $this->callApi($this->base_url."chain/get_required_keys",$post_required_keys);
           if(!isset($keys_out['required_keys'])) {
               var_dump($keys_out);
               exit("未查询到required_keys\r\n");
           }
           $required_keys= $keys_out['required_keys'];
           $post_tx[0] = $transaction2;
           $post_tx[1] = $required_keys;
           $post_tx[2] = $chain_id;
           $tx_out = $this->callApi($this->wallet_url."wallet/sign_transaction",$post_tx);
           if(!isset($tx_out['signatures'])) {
               var_dump($tx_out);
               exit("生成签名出错\r\n");
           }
           $signatures = $tx_out['signatures'];
           $transaction3 = [
               "expiration"             =>  $expiration,
               "ref_block_num"          =>  $ref_block_number,
               "ref_block_prefix"       =>  $ref_block_prefix,
               "context_free_actions"   =>  [],
               "actions"                =>  $actions,
               "transaction_extensions" =>  []
           ];
           $post_push_tx = [
             "compression"              =>  "none",
             "transaction"              =>  $transaction3,
             "signatures"               =>  $signatures  
           ];
           $post_push_tx2 = [
             "compression"              =>  "none",
             "packed_context_free_data" =>  "",  
             "packed_trx"              =>  $bin,
             "signatures"               =>  $signatures  
           ];
           $output = $this->callApi($this->base_url."chain/push_transaction",$post_push_tx);
           return $output;
        }
        
         private function unlockAccount() {
            $this->openWallet();
            $odata = [
                $this->config->item("eosWallet"),
                $this->config->item("eosPWD")
            ];
            $out = commit_curl($this->wallet_url."wallet/unlock",false,1,$odata);
            return json_decode($out,true);
        }
        
        
        public function create() {
            $odata = $this->config->item("eosWallet");
            $out = commit_curl($this->wallet_url."wallet/create",false,1,$odata);
            var_dump($out);
        }
        public function wlist() {
            $this->openWallet();
            $out = commit_curl($this->wallet_url."wallet/list_wallets");
            var_dump($out);
        }
        public function importKey($key) {
            $this->unlockAccount();
            $key = isset($key)?$key:$_GET['key'];
            $odata = [
                $this->config->item("eosWallet"),
                $key
            ];
            $out = commit_curl($this->wallet_url."wallet/import_key",false,1,$odata);
            var_dump($out);
        }
        
        public function keyList() {
            $this->unlockAccount();
            $out = commit_curl($this->wallet_url."wallet/get_public_keys");
            return json_decode($out,true);
        }
        
        private function openWallet() {
            $odata = $this->config->item("eosWallet");
            $out = commit_curl($this->wallet_url."wallet/open",false,4,$odata);
            return $out;
        }
        
        private function get_abi_bin($from, $to, $quantity, $memo) {
           $abi['code'] = "eosio.token";
           $abi['action'] = "transfer";
           $abi['args']['from'] = $from;
           $abi['args']['to'] = $to;
           $abi['args']['quantity'] = $quantity;
           $abi['args']['memo'] = $memo;
           $out = commit_curl($this->base_url."chain/abi_json_to_bin",false,1,$abi);
           if(!isset(json_decode($out,true)['binargs'])){
               var_dump($abi);
               var_dump($out);
               return 0;
           }
           $res = json_decode($out,true)['binargs'];
           return $res;
        }
        
        private function get_block($block_num) {
            $odata['block_num_or_id'] = intval($block_num);
            $out = commit_curl($this->base_url."chain/get_block",false,1,$odata);
            return json_decode($out,true);
        }
        
        private function get_tx($tx_id) {
            $odata = [
              'id'  =>  $tx_id  
            ];
            $out = $this->callApi($this->base_url."history/get_transaction",$odata);
            return $out;
        }
}


