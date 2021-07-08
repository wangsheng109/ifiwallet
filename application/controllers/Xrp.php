<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Xrp extends MY_Controller {

            
	public function __construct()
        {
                parent::__construct();
                $this->load->model('Xrp_coin_model');
                $this->rpc_url_local = "http://localhost:".$this->config->item('xrpPort');
                $this->rpc_url = "https://s1.ripple.com:51234";
                $this->coin_name = "xrp";
                $this->coin_id = $this->config->item('xrp_coin_id');
                $this->chain_id = $this->get_chain_id($this->coin_id);
        }
        public function encrypt_tool($seed) {
            echo "private key:".encrypt($seed)."\r\n";
        }
        public function test_info() {
        //    $result = $this->callLocal("ledger_current");
            $result = $this->get_current_ledger();
            var_dump($result);
            echo "chain_id: ".$this->chain_id."\r\n";
            echo "coin_id: ".$this->coin_id."\r\n";
            echo "balance : ".$this->get_balance("rK56Pv6NYTYDKvW3VS74JhFVGLYj9y12iY")."\r\n";
        }
        
        private function get_current_ledger() {
            $result = $this->call("ledger_current");
            if(isset($result['ledger_current_index'])&&is_integer($result['ledger_current_index'])){
                return $result['ledger_current_index'];
            } else {
                var_dump($result);
                exit("Get Current Ledger Failed!\r\n");
            }
        }
        
	private function get_balance($address) {
            $params[0] =[
                    "account" => $address,
                ];
            $result = $this->call("account_info",$params);
            if(!isset($result['account_data'])){
                var_dump($result);
                exit("获取余额失败\r\n");
            }
            $balance = $result['account_data']['Balance'];
            $tmp_balance = $balance/(1.0E+6);
            return $tmp_balance;
        }
        
        public function newAccount() {
            
            $accounts_count = $this->Xrp_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $memo = time()+$i*1000000+rand(0,100000);
                $data['address'] = $memo;
                $data['account'] = $this->config->item('xrp_hw_address');
                $data['create_time'] = $data['update_time'] = time();
                $result = $this->Xrp_coin_model->create_account($data);
            }
            echo "成功创建了 ".$i." 个XRP新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['coin_id'] = $this->coin_id;
            //get next_start_squence and account_name for gxb coin
            $chain_data = $this->Xrp_coin_model->get_chain_data($this->chain_id);
            $next_start = $chain_data->next_start;
            $account_name = $this->config->item('xrp_hw_address');
            $new_next_start = $this->get_current_ledger();
            // get the account associate txs start from next_start_sequence from the chain
            $get_payments_params[0] = [
                "account"           =>  $account_name,
                "ledger_index_max"  =>  -1,
                "ledger_index_min"  =>  $next_start
            ];
            $list_payments = $this->call("account_tx",$get_payments_params);
            if(!isset($list_payments['transactions'])){
                var_dump($get_payments_params);
                var_dump($list_payments);
                exit("List transactions error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            $payments = $list_payments['transactions'];
            $txs = array();
            foreach($payments as $payment) {
                if(!isset($payment['tx'])){
                    continue;
                }
                $tx = $payment['tx'];
                $meta = $payment['meta'];
                if($tx['Destination']!=$account_name||$tx['TransactionType']!="Payment"){
                    continue;
                }
                $is_duplicate = $this->Xrp_coin_model->check_deposit_log_duplicate($tx["hash"],$tx['DestinationTag']);
                if(intval($is_duplicate)>0) {
                    echo "已经存在该交易：".$tx["hash"]."\r\n";
                    continue;
                }
                $cold_wallet_array = $this->Xrp_coin_model->get_cold_wallet(['address'=>$tx['DestinationTag']],true);
                if(!isset($cold_wallet_array[0])) {
                    continue;
                }
                $cold_wallet = $cold_wallet_array[0];
                if(isset($meta['delivered_amount']) && $meta['delivered_amount']>0){
                    $value = $meta['delivered_amount']/(1.0E+6);
                } else {
                   $value = $tx['Amount']/(1.0E+6); 
                }
                $log_db_data['user_id'] = $cold_wallet['user_id'];
                $log_db_data['address'] = $cold_wallet['address'];
                $log_db_data['amount'] = $value;
                $log_db_data['tx_hash'] =  $tx["hash"];
                $log_db_data['tx_timestamp'] = intval($tx["date"])+946684800;
                $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                $log_db_data['status'] = 100;
                $db_amount = $cold_wallet['balance'];
                $db_amount = floatval($db_amount)+floatval($value);
                $this->Xrp_coin_model->insert_db("coin_deposit_log",$log_db_data);
                $cold_wallet_db['balance'] = $db_amount;
                $this->Xrp_coin_model->update_cold_wallet($cold_wallet_db,$cold_wallet['id']);
                $complete +=1;
            }
            $this->Xrp_coin_model->update_chain_data(["next_start"=>$new_next_start],$this->chain_id); 
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        
        public function depositConfirm() {
            $deposits = $this->Xrp_coin_model->get_deposit_log();
            
            foreach($deposits as $item) {
                $tx = $this->get_tx($item['tx_hash']);
                if (empty($tx)) {
                    echo $item['tx_hash']." tx not broadcasted...\r\n";
                    continue;
                }
                if (isset($tx['validated'])&& $tx['validated']&&isset($tx['meta']['delivered_amount'])) {
                    $data['status'] = 200;
                    $data['update_time'] = time();
                    $this->Xrp_coin_model->update_deposit_log($data,$item['id']);
                    echo $item['tx_hash']." tx deposit log updated\r\n";
                    $this->Xrp_coin_model->update_user_wallet($item);
                    echo $item['tx_hash']." user wallet updated\r\n";
                    
                } else {
                    echo $item['tx_hash']." tx not broadcasted...\r\n";
                    continue;
                }
            }
        }
        public function withdraw() {
            $count = [
                'complete' =>0,
                'drop'     =>0,
                'data'     =>''
            ];
            $res = $this->Xrp_coin_model->get_withdraw_log(200);
            if(empty($res)||count($res)==0) {
                exit("No need to withdraw\r\n");
            }
            $from = $this->config->item('xrp_hw_address');
            $seed = decrypt($this->config->item('xrp_hw_secret'));
            $complete = 0;
            foreach($res as $item) {
                $to = $item['coin_address'];
                $value = $item['coin_actual_amount'];
                $cost = calculate($value,$this->config->item('xrpWithdrawReserved'),'bcadd');
                $cost = calculate($cost,$this->config->item('xrp_fee'),'bcadd');
                $left = calculate(floatval(strval($this->get_balance($from))),$cost,'bcsub');
                if($left<0){
                    $output['to']=$to;
                    $output['value']=$value;
                    $output['cost']=$cost;
                    $output['balance']=$this->get_balance($from);
                    $output['left']=$left;
                    var_dump($output);
                    echo "热钱包余额不足!\r\n";
                    continue;
                }
                $memo = $item['memo'];
                $tx_hash = $this->gen_tx($from,$to,$value,$seed,$memo);
                if(!empty($tx_hash)) {
                    $id = $item['id'];
                    $data['tx_hash'] = $tx_hash;
                    $data['status'] = 210;
                    $data['update_time'] = time();
                    $complete += 1;
                    $this->Xrp_coin_model->update_withdraw_log($data,$id);
                } else {
                    $id = $item['id'];
                    $data['status'] = 400;
                    $data['update_time'] = time();
                    $this->Xrp_coin_model->update_withdraw_log($data,$id);
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Xrp_coin_model->get_withdraw_log(210);
            $complete=0;
            foreach($res as $item) {
                $id = $item['id'];
                if (empty($item['tx_hash'])) {
                    echo "查询transaction异常!\r\n";
                    continue;
                } else {
                    $tx = $this->get_tx($item['tx_hash']);
                    if (empty($tx)) {
                        echo "交易等待广播中...\r\n";
                        continue;
                    }
                    if (isset($tx['validated'])&& $tx['validated']&&isset($tx['meta']['delivered_amount'])) {
                        $data['status'] = 280;
                        $complete += 1;
                        $this->Xrp_coin_model->update_withdraw_log($data,$id);
                    } else {
                        echo "交易等待广播中...\r\n";
                        continue;
                    }
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        private function toAtom($value) {
            $float = $value*(1.0E+6);
            return number_format($float,0,'.','');
        }
        
        private function gen_tx($from,$to,$value,$seed,$tag="0") {
            if(!ctype_digit(strval($tag))||$tag==""){
                $tag="1234567890";
            }
            $tx_json = [
              "Account" =>  $from,
              "Amount"  =>  intval($this->toAtom(floatval($value))),
              "Destination"       => $to,
              "LastLedgerSequence"  => intval($this->get_current_ledger())+4,
              "DestinationTag"    => $tag,  
              "TransactionType"   => "Payment"
            ];
            $data[0] = [
                "secret"            => $seed,
                "tx_json"           => $tx_json,
                "fee_mult_max"      => intval($this->toAtom($this->config->item('xrp_fee')))
            ];
            $output = $this->callLocal("sign",$data);
            if(!isset($output['tx_blob'])){
                var_dump($data);
                var_dump($output);
                exit("生成交易签名失败!\r\n");
            }
            $tx_blob = $output['tx_blob'];
            $params[0] = [
              "tx_blob"     =>  $tx_blob
            ];
            $result = $this->call("submit", $params);
            if(!isset($result['tx_json']['hash'])||intval($result['engine_result_code'])!=0) {
                var_dump($result);
                exit("提交交易失败!\r\n");
            }
            return $result['tx_json']['hash'];
        }
        
        private function get_tx($txid) {
            $data[0] =[
                "transaction"=>$txid
            ];
            $tx = $this->call("tx",$data);
            return $tx;
        }
        
}


