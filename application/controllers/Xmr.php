<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Xmr_new extends MY_Controller {

	public function __construct()
        {
                parent::__construct();
                $this->load->helper('url_helper');
                $this->load->model('Xmr_coin_model');
                $this->rpc_url = "http://localhost:".$this->config->item('xmrPort')."/json_rpc";
                $this->coin_name = "xmr";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('xmr_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')."\r\n");
                }
        }
        public function test_balance($account) {
            $out = $this->get_unlocked_balance(intval($account));
            var_dump($out);
        }
        
	private function unlockWallet() {
            $params = [
                "filename"  =>  $this->config->item("xmrWalletFile"),
                "password"  =>  $this->config->item("xmrPWD")
            ];
            $out = $this->call("open_wallet",$params);
            var_dump($out);
        }
        
        private function stopWallet() {
            $out = $this->call("stop_wallet");
            var_dump($out);
        }
        
        
        private function get_balance($account_index) {
            $params =[
                    "transfer_type" => "available",
                    "account_index" => $account_index
                ];
            $result = $this->call("incoming_transfers",$params);
            if(!isset($result['transfers'])){
                var_dump($result);
                var_dump($params);
                exit("获取余额失败\r\n");
            }
            $unspents = $result['transfers'];
            if(empty($unspents)) {
                return 0;
            }
            $balance = 0;
            foreach($unspents as $unspent) {
                if($unspent['spent']){
                    continue;
                }
                $tmp_amount = number_format($unspent['amount'], getFloatLength($unspent['amount']), '.', '');
                $max_float = getFloatLength($balance)>getFloatLength($unspent['amount'])? getFloatLength($balance):getFloatLength($unspent['amount']);
                $balance = bcadd($balance, $tmp_amount, $max_float);
            }
            $tmp_balance = $balance/(1.0E+12);
            $value = number_format($tmp_balance,getFloatLength($tmp_balance));
            return $value;
        }
        
        private function get_unlocked_balance($account_index) {
            $params =[
                    "account_index" => $account_index
                ];
            $result = $this->call("getbalance",$params);
            if(!isset($result['unlocked_balance'])){
                var_dump($result);
                var_dump($params);
                exit("获取余额失败\r\n");
            }
            $balance = $result['unlocked_balance'];
            $tmp_balance = $balance/(1.0E+12);
            $value = number_format($tmp_balance,getFloatLength($tmp_balance));
            return $value;
        }
        
        public function newAccount() {
            $accounts_count = $this->Xmr_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0; $i<$this->config->item('loopTime'); $i++) {
                $params = [
                    "label" => "xmrlabel".time()
                ];
                $account = $this->call("create_account",$params);
                if(empty($account)){
                    continue;
                }
                $data['account']= $account['account_index'];
                $data['address'] = $account['address'];
                $data['create_time'] = $data['update_time'] = time();
                $this->Xmr_coin_model->create_account($data);
            }
            echo "成功创建了 ".$i." 个XMR新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            //get next_start_squence and account_name for gxb coin
            $chain_data = $this->Xmr_coin_model->get_chain_data($this->coin_name);
            $next_start = $chain_data->next_start_sequence;
            $get_payments_params = [
                "min_block_height"  =>  $next_start
            ];
            $list_payments = $this->call("get_bulk_payments",$get_payments_params);
            if(!isset($list_payments['payments'])){
                exit("List payments error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            $payments = $list_payments['payments'];
            foreach($payments as $item) {
                $value = $item['amount']/(1.0E+12);
                $address = $item['address'];
                //retrieve info such as balance from cold wallet by the address(memo) and coin_id
                $cold_wallet_array = $this->Xmr_coin_model->get_cold_wallet(['address'=>$address],true);
                if(!isset($cold_wallet_array[0])) {
                    echo "地址不在冷钱包数据库里面\r\n";
                    continue;
                }
                $cold_wallet = $cold_wallet_array[0];
                $db_amount = floatval($cold_wallet['balance']);
                $log_db_data['uid'] = $cold_wallet['uid'];
                $log_db_data['address'] = $cold_wallet['address'];
                $log_db_data['amount'] = $value;
                $log_db_data['tx_hash'] =  $item['tx_hash'];
                $log_db_data['transfer_time'] = $this->b2t(intval($item['block_height']));
                $log_db_data['remark'] = $item['block_height'];
                $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                $db_amount = $cold_wallet['balance'];
                $db_amount = calculate($db_amount,$value,"bcadd");
                $this->Xmr_coin_model->insert_db("coin_deposit_log",$log_db_data);
                $cold_wallet_db['balance'] = $db_amount;
                $this->Xmr_coin_model->update_cold_wallet($cold_wallet_db,$cold_wallet['id']);
                $complete +=1;
            }
            $new_next_start = intval($this->call("getheight")['height']);
            $this->Xmr_coin_model->update_chain_data(["next_start_sequence"=>$new_next_start],$this->coin_name); 
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n";            
        }
        
        public function depositConfirm() {
            $deposits = $this->Xmr_coin_model->get_deposit_log();
            
            foreach($deposits as $item) {
                if(empty($item['remark'])||$item['remark']=""|| !is_numeric($item['remark'])){
                    $start = 0;
                } else {
                    $start = intval($item['remark']);
                }
                $get_payments_params = [
                    "min_block_height"  =>  $start
                ];
                $list_payments = $this->call("get_bulk_payments",$get_payments_params);
                if(!isset($list_payments['payments'])){
                    continue;
                }
                $payments = $list_payments['payments'];
                $transaction = array();
                foreach($payments as $tx) {
                    if($tx['tx_hash'] == $item['tx_hash']) {
                        $transaction = $tx;
                        break;
                    }
                }
                if (empty($tx)) {
                    echo "tx hash : ".$item['tx_hash']."\r\n";
                    echo "交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['block_height'])&&intval($tx['block_height'])>0) {
                    $data['confirmations'] = intval($this->call("getheight")['height'])-intval($tx['block_height']);
                    $data['update_time'] = time();
                    $this->Xmr_coin_model->update_deposit_log($data,$item['id']);
                    echo $item['tx_hash']." 交易确认数更新\r\n";
                    echo "交易广播成功\r\n";
                } else {
                    echo "tx hash : ".$item['tx_hash']."\r\n";
                    echo "交易等待广播中...\r\n";
                    var_dump($tx);
                }
            }
        }
        
        public function withdraw() {
            $count = [
                'complete' =>0,
                'drop'     =>0,
                'data'     =>''
            ];
            $res = $this->Xmr_coin_model->get_withdraw_log(200);
            if(empty($res)||count($res)==0) {
                exit("No need to withdraw\r\n");
            }
            $total_value = $total_fee = 0;
            $destinations=array();
            $i=0;
            foreach($res as $tx) {
                $to = $tx['coin_address'];
                $value = $tx['coin_actual_amount'];
                $destinations[$i] =[
                    "amount"    =>  $this->toAtom($value),
                    "address"   =>  $to
                ];
                $total_value +=$value;
                $i++;
            }
            $total_fee = $this->config->item('xmr_tx_fee')*$i;
            $hw_balance = $this->get_unlocked_balance($this->config->item('xmr_pay_account_index'));
            if ($hw_balance<$total_value+$total_fee) {
                    exit("热钱包余额不足!\r\n");
            }
            $complete=0;
            $tx_hash = $this->gen_tx($destinations, $this->config->item('xmr_pay_account_index'));
            if(isset($tx_hash['tx_hash'])){
                foreach($res as $tx) {
                    $id = $tx['id'];
                    $data['tx_hash'] = $tx_hash['tx_hash'];
                    $data['status'] = 210;
                    $data['update_time'] = time();
                    $complete += 1;
                    $this->Xmr_coin_model->update_withdraw_log($data,$id);
                }
            } else {
                foreach($res as $tx) {
                    $id = $tx['id'];
                    //防止网络抖动造成误判，手动解决
                     continue;
//                    $data['status'] = 400;
//                    $data['update_time'] = time();
//                    $this->Xmr_coin_model->update_withdraw_log($data,$id);
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Xmr_coin_model->get_withdraw_log(210);
            $complete=0;
            foreach($res as $item) {
                $id = $item['id'];
                if (empty($item['tx_hash'])) {
                    echo "查询transaction异常!\r\n";
                    continue;
                } else {
                    $tx = $this->call("get_transfer_by_txid",["txid"=>$item['tx_hash']]);
                    var_dump($tx);
                    if (empty($tx)) {
                        echo "交易等待广播中...\r\n";
                        continue;
                    }
                    if (isset($tx['transfer'])&&$tx['transfer']['type']=="out") {
                        $data['status'] = 280;
                        $data['update_time'] = time();
                        $complete += 1;
                        $this->Xmr_coin_model->update_withdraw_log($data,$id);
                    } else {
                        echo "交易等待广播中...\r\n";
                        continue;
                    }
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Withdraw Money to the platform wallet
        public function withdrawSys() {
            $threshold = $this->Xmr_coin_model->get_threshold();
            $sum_balance = $this->Xmr_coin_model->get_sum_balance();
            if($threshold > $sum_balance){
                exit("总资产未达到提现阔值:{$sum_balance}\r\n");
            }
            $res = $this->Xmr_coin_model->get_withdraw_sys($this->config->item('xmr_tx_fee'));
            $to = $this->config->item('xmrWithdrawTo');
            $complete = 0;
            foreach($res as $item) {
                $available_balance = $this->get_unlocked_balance(intval($item['account']));
                $fee = $this->config->item('xmr_tx_fee');
                if($available_balance < $fee) {
                    continue;
                }
                $value = calculate($available_balance,$fee,"bcsub");
                $destinations[0] = [
                  "amount"        =>    $this->toAtom($value),
                  "address"       =>    $to
                ];
                $tx_hash = $this->gen_tx($destinations, intval($item['account']));
                if(isset($tx_hash['tx_hash'])){
                    $data['cid'] = $item['id'];
                    $data['tx_hash'] = $tx_hash['tx_hash'];
                    $data['coin_id'] = $this->config->item('xmr_coin_id');
                    $data['status'] = 210;
                    $data['amount'] = $item['balance'];
                    $data['address'] = $item['address'];
                    $data['to_address'] = $to;
                    $data['create_time'] = time();
                    $this->Xmr_coin_model->insert_withdraw_sys_log($data);
                    $complete += 1;
                } else {
                    echo "系统提现交易失败,时间:".date('Y-m-d H:i:s')."\r\n";
                    $put_out = $destinations[0];
                    $put_out['account_index'] = $item['account'];
                    $put_out['available_balance'] = $available_balance;
                    $put_out['fee'] = $fee;
                    $put_out['value'] = $value;
                    $put_out['tx_output'] = $tx_hash;
                    var_dump($put_out);
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Confirm sys Withdraw
        public function withdrawSysConfirm() {
            $res = $this->Xmr_coin_model->get_withdraw_sys_confirm();
            $complete = 0;
            foreach($res as $item) {
                $tx = $this->call("get_transfer_by_txid",["txid"=>$item['tx_hash']]);
                
                if (empty($tx)) {
                    var_dump($tx);
                    echo "tx hash : ".$item['tx_hash']."\r\n";
                    echo "交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['transfer'])&&$tx['transfer']['type']=="out") {
                    $amount = intval($tx['transfer']['amount']);
                    $fee = intval($tx['transfer']['fee']);
                    $cost = ($amount+$fee)/(1.0E+12);
                    $value = number_format($cost,getFloatLength($cost));
                    $data['status'] = 280;
                    $cold['balance'] = calculate($item['amount'],$cost,"bcsub");
                    $this->Xmr_coin_model->update_cold_wallet($cold,$tx['cid']);
                    $complete += 1;
                    echo "交易广播成功\r\n";
                } else {
                    echo "交易失败\r\n";
                    $data['status'] = 400;
                    var_dump($tx);
                }
                $this->Xmr_coin_model->update_sys_withdraw($data,$item['id']);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        private function toAtom($value) {
            $float = $value*(1.0E+12);
            return number_format($float,0,'.','');
        }
        
        public function test_tx() {
            $des = ["amount"=> $this->toAtom(0.6),"address"=>"9wNgSYy2F9qPZu7KBjvsFgZLTKE2TZgEpNFbGka9gA5zPmAXS35QzzYaLKJRkYTnzgArGNX7TvSqZC87tBLwtaC5RQgJ8rm"];
            $destinations[0] = $des;
            $out = $this->gen_tx($destinations,0);
            var_dump($out);
        }
        
        
        private function gen_tx($destinations,$accountIndex) {
            
            $data = [
                "destinations"      => $destinations,
                "account_index"     => $accountIndex,
                "subaddr_indices"   => [0],
            //    "fee"               => $this->toAtom($this->config->item('xmr_tx_fee')),
                "mixin"             => 4,
                "get_tx_key"        => true
            ];
            $output = $this->call("transfer",$data);
            return $output;
        }
        
        private function sweep_dust() {
            $this->call("sweep_dust");
        }
        
        private function sweep_all(){
            $data = [
                "address"   =>  "9ui2G3b4zgRZmDFQuFmE1ARi9jRqQMnYFSDnvGx1UL4s2LRr69vWxkmdJ345SE23g5ExubUkaJEBZ2hjd47JAiBgLmhHNmr",
                "account_index" =>0
            ];
            $out = $this->call("sweep_all",$data);
            var_dump($out);
        }
        
        public function test_get_tx($txid) {
            $out = $this->get_transfer_by_txid($txid);
            var_dump($out);
        }
        
        private function get_transfer_by_txid($txid) {
            $data =[
                "txid"=>$txid
            ];
            var_dump($data);
            $tx = $this->call("get_transfer_by_txid",$data);
            return $tx;
        }
        
        // block numbers to time stamp
        private function b2t($blocks) {
            $current_blocks = $this->call("getheight")['height'];
            $thetime = time() - ($current_blocks-$blocks)/118;
            return intval($thetime);
        }
        
}


