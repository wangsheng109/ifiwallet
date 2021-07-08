<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ont extends CI_Controller {

	public function __construct()
        {
                parent::__construct();
                $this->load->model('Ada_model');
                $this->load->helper('url_helper');
        }
        public function test_rpc() {
            $out = $this->call("getblockcount");
            var_dump($out);
        }
        private function call($method, $params=NULL) {
            if(empty($params)){
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                'id' =>time()
                ];
            } else {
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                "params"=> $params,
                'id' =>time()
                ];
            }
            $out = commit_curl("http://localhost:20336",false,1,$odata);
            if(isset(json_decode($out,true)['result'])){
                return json_decode($out,true)['result'];
            } else {
                if(isset(json_decode($out,true)['error'])){
                    $error = json_decode($out,true)['error'];
                    $error['method'] = $method;
                    $error['when'] = date('Y-m-d H:i:s');
                    var_dump($error);
                }
                return null;
            }
        }
        
	private function unlockWallet() {
            $params = [
                "filename"  =>  $this->config->item("xmrWalletFile"),
                "password"  =>  $this->config->item("xmrPWD")
            ];
            $out = $this->call("open_wallet",$params);
            var_dump($out);
        }
        
        private function lockWallet() {
            $out = $this->call("walletlock",[]);
            var_dump($out);
        }
        
        private function get_balance($account_index) {
            $params =[
                    "transfer_type" => "available",
                    "account_index" => $account_index
                ];
            $result = $this->call("incoming_transfers",$params);
            if(!isset($result['transfers'])){
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
                exit("获取余额失败\r\n");
            }
            $balance = $result['unlocked_balance'];
            $tmp_balance = $balance/(1.0E+12);
            $value = number_format($tmp_balance,getFloatLength($tmp_balance));
            return $value;
        }
        
        public function newAccount() {
            $res = '';
            $accounts_count = $this->Ada_model->get_coin_accounts_count($this->config->item('xmr_coin_id'));
            if($accounts_count > $this->config->item('xmr_max_accounts')) {
                throw new Exception('可用账户充足');
            }
            $this->unlockWallet();
            for($i=0; $i<$this->config->item('loopTime'); $i++) {
                $params = [];
                $account = $this->call("create_account",$params);
                if(empty($account)){
                    continue;
                }
                $data['coin_id'] = $this->config->item('xmr_coin_id');
                $data['btc_account_name']= $account['account_index'];
                $data['address'] = $account['address'];
                $data['create_time'] = $data['utime'] = time();
                $res = $res." ".$this->Ada_model->create_account($data);
            }
           $this->output ->set_content_type('application/json') ->set_output($res);
        }
        
        public function deposit() {
            $coin_data = $this->Ada_model->get_coin_data($this->config->item('xmr_coin_id'));
            $next_start = $coin_data->next_start_sequence;
            if(!isset($this->call("getheight")['height'])){
                exit("Get block height error! \r\n");
            }
            $complete = 0;
            $wrap_txs = $this->wrap_tx_by_address($next_start);
            foreach($wrap_txs[1] as $key => $txs) {
                                //retrieve info such as balance from cold wallet by the address(memo) and coin_id
                $active = $this->Ada_model->get_active($this->config->item('xmr_coin_id'),['address'=>$key],true);
                if(empty($active)){
                    continue;
                }
                $cold_wallet = $active[0];
                $db_amount = floatval($cold_wallet['balance']);
                
             // wrap content
                $to_post = array();
                $to_post['address'] = $cold_wallet['address'];
                $to_post['balance'] = $db_amount;
                $to_post['symbol'] = "xmr";
                $txs_filter = array();
                $i = 0;
                foreach($txs as $tx) {
                    $txs_filter[$i]['amount'] = strval($tx["value"]);
                    $txs_filter[$i]['hash'] =  $tx["id"];
                    $txs_filter[$i]['timestamp'] =  strval($tx["latest_time"]);
                    $to_post['balance'] +=$tx['value'];
                    $i++;
                }
                if($to_post['balance'] > $db_amount) {
                    $to_post['txs'] = $txs_filter;
                    $to_post['balance'] = strval($to_post['balance']);
                    $content = json_encode($to_post);
                    // sign content
                    $sign = $this->sign_content($content);
                    $to_post['sign'] = $sign;
                    //call api
                    $output = commit_curl($this->config->item("bgj_api_url")."/api/wallet/deposit",false,2,$to_post);
                    if(!strpos($output,'100')||!strpos($output,"success")){
                        var_dump($output);exit;
                    }
                    $complete +=1;
                }
            }
            $sqldata['next_start_sequence'] = $wrap_txs[0];
            $this->Ada_model->update_coin_token($sqldata,$this->config->item('xmr_coin_id'));
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n";
        }
        
        private function wrap_tx_by_address($next_start) {
            $get_payments_params = [
                "min_block_height"  =>  $next_start
            ];
            $list_payments = $this->call("get_bulk_payments",$get_payments_params);
            if(!isset($list_payments['payments'])){
                exit("List payments error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            $payments = $list_payments['payments'];
            $txs=array();
            foreach($payments as $item) {
                $tmp_value = $item['amount']/(1.0E+12);
                $value = number_format($tmp_value,getFloatLength($tmp_value));
                $tmp_array = [
                  'id'      =>  $item['tx_hash'],
                  'value'   =>  $value,
                  'latest_time'=>$this->b2t(intval($item['block_height'])),
                ];
                if(isset($txs[$item['address']])){
                    array_push($txs[$item['address']],$tmp_array);
                } else {
                    $txs[$item['address']][0]   = $tmp_array;
                }
            }
            $result[0]=intval($this->call("getheight")['height']);
            $result[1]=$txs;
            return $result;
        }
        
        private function sign_content($content) {
            $sault = "PAH535BJn/kfWkN9puk=";
            $sign = md5(md5($content).$sault);
            return $sign;
        }
        
        public function withdraw() {
            $count = [
                'complete' =>0,
                'drop'     =>0,
                'data'     =>''
            ];
            $res = $this->Ada_model->get_withdraw($this->config->item('xmr_coin_id'));
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
                    $this->Ada_model->update_withdraw($data,$id);
                }
            } else {
                foreach($res as $tx) {
                    $id = $tx['id'];
                    //防止网络抖动造成误判，手动解决
                     continue;
//                    $data['status'] = 400;
//                    $data['update_time'] = time();
//                    $this->Ada_model->update_withdraw($data,$id);
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Ada_model->get_withdraw_confirm($this->config->item('xmr_coin_id'));
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
                    if (isset($tx['transfer'])) {
                        $data['status'] = 280;
                        $complete += 1;
                        $this->Ada_model->update_withdraw($data,$id);
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
            $count = [
              'complete'    =>  0,
                'drop'      =>  0,
                'data'      =>  ''
            ];
            $theshold = $this->Ada_model->get_theshold($this->config->item('bch_coin_id'));
            $sum_balance = $this->Ada_model->get_sum_balance($this->config->item('bch_coin_id'));
            if($theshold > $sum_balance){
                exit("总资产未达到提现阔值:{$sum_balance}\r\n");
            }
            $res = $this->Ada_model->get_withdraw_sys($this->config->item('ada_coin_id'),0.2);
            $to = $this->config->item('bchWithdrawTo');
            
            foreach($res as $tx) {
                $data['cid'] = $tx['id'];
                $data['address'] = $tx['address'];
                $value = $this->get_account($tx['btc_account_name'])['data']['amount']/1000000-0.2;
                $check_duplicate = $this->Ada_model->get_withdraw_sys_confirm($this->config->item('ada_coin_id'),$tx['id']);
                if(is_array($check_duplicate)&&count($check_duplicate)>0) {
                    continue;
                }
                $output = $this->gen_tx($value,intval($tx['btc_account_name']),$this->config->item('adaWithdrawTo'));
                if(!isset(json_decode($output,true)['data'])) {
                    $count['data'] = $count['data']."\n".$output;
                    continue;
                }
                $hash = json_decode($output,true)['data']['id'];
                $data['tx_hash'] = $hash;
                if($hash === NULL) {
                    $data['status'] = 400;
                    $data['amount'] = $tx['balance'];
                    $data['to_address'] = $to;
                    $count['drop'] += 1;
                } else {
                    $data['coin_id'] = $this->config->item('ada_coin_id');
                    $data['status'] = 210;
                    $data['amount'] = $tx['balance'];
                    $data['to_address'] = $to;
                    $count['complete'] += 1;
                }
                $data['create_time'] = time();
                $this->Ada_model->insert_withdraw_log($data);
            }
            if($count['data']!='') {
                $str_res = $count['data'];
            } else {
                $str_res = json_encode($count);
            }
            $this->output->set_output($str_res);
        }
        
        //Confirm sys Withdraw
        public function withdrawSysConfirm() {
            $res = $this->Ada_model->get_withdraw_sys_confirm($this->config->item('ada_coin_id'));
            $count = [
                'complete' => 0,
                'drop'     => 0,
                'data'     => ''
            ];
            foreach($res as $tx) {
                $id = $tx['id'];
                $odata = [
                "wallet_id" => $this->config->item('wallet_id'),
                "id" => $tx['tx_hash']
                ];
                $out = commit_curl("https://localhost:8090/api/v1/transactions?". http_build_query($odata),true,0);
                $result = json_decode($out,true)['data'][0];
                if( $result == null) {
                    $count['data'] = $count['data']."\n".$out;
                    $data['status'] = 400;
                    $count['drop'] += 1;
                } else if(isset($result['confirmations']) && hexdec($result['confirmations']) > 6) {
                    $data['status'] = 280;
                    $count['complete'] += 1;
                    // update cold wallet
                    $outputs = $result['outputs'];
                    $inputs = $result['inputs'];
                    $total_outputs = $total_inputs = 0;
                    $transfer_value = 0;
                    foreach($inputs as $input) {
                        $total_inputs += floatval($input['amount']/1000000);
                    }
                    foreach($outputs as $output) {
                        $total_outputs += floatval($output['amount']/1000000);
                        if($output['address'] == $tx['to_address']) {
                            $transfer_value = floatval($output['amount']/1000000);
                        }
                    }
                    $fee = $total_inputs - $total_outputs;
                    $final_amount = $tx['amount'] - $transfer_value - $fee;
                    /*$final_amount = 0;
                    foreach($outputs as $output) {
                        if($output['address']!=$tx['to_address']){
                            $final_amount=floatval($output['amount']/1000000);
                        }
                    }*/
                    $cold['balance'] = $final_amount;
                    $this->Ada_model->update_cold_wallet($cold,$tx['cid']);
                } else {
                    continue;
                }
                $this->Ada_model->update_sys_withdraw($data,$id);
            }
            if($count['data']!='') {
                $str_res = $count['data'];
            } else {
                $str_res = json_encode($count);
            }
            $this->output->set_output($str_res);
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
                "fee"               => $this->toAtom($this->config->item('xmr_tx_fee')),
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
        
        private function get_transfer_by_txid($txid) {
            $data =[
                "txid"=>$txid
            ];
            var_dump($data);
            $tx = $this->call("get_transfer_by_txid",$data);
        }
        
        // block numbers to time stamp
        private function b2t($blocks) {
            $current_blocks = $this->call("getheight")['height'];
            $thetime = time() - ($current_blocks-$blocks)/118;
            return intval($thetime);
        }
        
}


