<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Etc_new extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->helper('url_helper');
                $this->load->model('Etc_coin_model');
                $this->rpc_url = "http://localhost:8545";
                $this->coin_name = "etc";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('etc_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')."\r\n");
                }
        }
        
        public function decrypt_tool() {
            echo decrypt($this->config->item('test_private_key'))."\r\n";
        }
        
        public function encrypt_tool($privateKey) {
            echo encrypt($privateKey)."\r\n";
        }
        
        public function newAccount() {
            $accounts_count = $this->Etc_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $privateKey = bin2hex(openssl_random_pseudo_bytes(32));
                $pkey = $this->importKey($privateKey);
                $data['address'] = $pkey;
                $data['private_key'] = encrypt($privateKey);
                $data['create_time'] = $data['update_time'] = time();
                $this->Etc_coin_model->create_account($data);
            }
            $this->deleteDir($this->config->item('keystorePath'));
            echo "成功创建了 ".$i." 个ETC新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            $res = $this->Etc_coin_model->get_cold_wallet();
            foreach($res as $item) {
                $db_amount = floatval($item['balance']);
                $address = $item['address'];
                $raw_txs_output = commit_curl("http://api.gastracker.io/addr/".$address."/transactions");
                if(!isset(json_decode($raw_txs_output,true)['items'])){
                    echo "地址 ".$address." 没有任何交易\r\n";
                    continue;
                }
                $raw_txs = json_decode($raw_txs_output,true)['items'];
                date_default_timezone_set('UTC');
                $latest_time = 0;
                $db_amount = $item['balance'];
                foreach($raw_txs as $raw_tx) {
                    if(strtotime($raw_tx['timestamp'])>intval($item['update_time']) && strtolower(strval($raw_tx['to']))==strtolower(strval($address))) {
                        if(strtotime($raw_tx['timestamp']) > $latest_time) {
                            $latest_time = strtotime($raw_tx['timestamp']);
                            $cold_wallet_db['update_time'] = $latest_time;
                        }
                        $tmp_value = $raw_tx['value']['wei']/(1.0E+18);
                    //    $value = number_format($tmp_value,getFloatLength($tmp_value));
                        $floatLength = intval(getFloatLength($tmp_value));
                        $value = sprintf("%.{$floatLength}f",floatval($tmp_value));
                        $log_db_data['uid'] = $item['uid'];
                        $log_db_data['address'] = $address;
                        $log_db_data['amount'] = $value;
                        $log_db_data['tx_hash'] =  $raw_tx['hash'];
                        $log_db_data['transfer_time'] = strtotime($raw_tx['timestamp']);
                        $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                        $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                        $this->Etc_coin_model->insert_db("coin_deposit_log",$log_db_data);
                        $db_amount = calculate($db_amount,$value,"bcadd");
                        $cold_wallet_db['balance'] = $db_amount;
                        $this->Etc_coin_model->update_cold_wallet($cold_wallet_db,$item['id']);
                        $complete +=1;
                    }
                }
            }
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n"; 
        }
        //查询本地节点，远程api不可用时使用
        public function depositConfirm2() {
            $latest_blocks = hexdec($this->call("eth_blockNumber"));
            $deposits = $this->Etc_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $method  = "eth_getTransactionByHash";
                $params  = [$tx_hash];
                $tx = $this->call($method,$params);
                if (empty($tx)) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['blockNumber']) && hexdec($tx['blockNumber']) >0) {
                    $data['confirmations'] = $latest_blocks - hexdec($tx['blockNumber']);
                    $data['update_time'] = time();
                    $this->Etc_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }
        //查询远程节点，本地节点不可用时使用
        public function depositConfirm() {
            $latest_blocks = hexdec($this->callRemote("eth_blockNumber"));
            $deposits = $this->Etc_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $method  = "eth_getTransactionByHash";
                $params  = [$tx_hash];
                $tx = $this->callRemote($method,$params);
                if (empty($tx)) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['blockNumber']) && hexdec($tx['blockNumber']) >0) {
                    $data['confirmations'] = $latest_blocks - hexdec($tx['blockNumber']);
                    $data['update_time'] = time();
                    $this->Etc_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }
        
        private function get_balance($address, $model="gastracker") {
            $result = "";
            if($model == "local") {
                $method="eth_getBalance";
                $params=[$address,"latest"];
                $result = hexdec($this->call($method,$params));
            } else if ($model == "gastracker") {
                $out = commit_curl("http://api.gastracker.io/addr/".$address);
                $result = json_decode($out,true)['balance']['wei']/(1.0E+18);
            }
            return $result;
        }
        // Withdraw money to user
        public function withdraw() {
            $complete = 0;
            $res = $this->Etc_coin_model->get_withdraw_log(200);
            foreach($res as $item) {
                $id = $item['id'];
                $hw_balance = $this->get_balance($this->config->item('etcPayAccount'));
                $total_cost = calculate(strval(0.0005),$item['coin_actual_amount'],"bcadd");
                $left = calculate($hw_balance,$total_cost,"bcsub");
                if($left < 0) {
                    echo "热钱包余额不足,热钱包余额: ".$hw_balance." ,实际需要花费: ".$total_cost." \r\n";
                    continue;
                }
                $tx_hash = $this->gen_tx($item['coin_actual_amount'],$this->config->item('etcPayAccount'),$item['coin_address']);
                if(empty($tx_hash)||is_array($tx_hash) ) {
                     //防止网络抖动造成误判
                     continue;
//                    $data['status'] = 400;
//                    $data['update_time'] = time();
                } else {
                    $data['tx_hash'] = $tx_hash;
                    $data['status'] = 210;
                    $data['update_time'] = time();
                    $complete += 1;
                }
                $this->Etc_coin_model->update_withdraw_log($data,$id);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Etc_coin_model->get_withdraw_log(210);
            $complete = 0;
            foreach($res as $tx) {
                $id = $tx['id'];
                $method  = "eth_getTransactionByHash";
                $params  = [$tx['tx_hash']];
                $result = $this->call($method,$params);
                if( $result == null || $result['blockNumber'] == null) {
                    var_dump($result);
                    echo "emtpy tx : ".$tx['tx_hash']."\r\n";
                    continue;
                } else if(isset($result['blockNumber']) && hexdec($result['blockNumber']) >0) {
                    $data['status'] = 280;
                    $data['update_time'] = time();
                    $complete += 1;
                    $this->Etc_coin_model->update_withdraw_log($data,$id);
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Withdraw Money to the platform wallet
        public function withdrawSys() {
            $threshold = $this->Etc_coin_model->get_threshold();
            $sum_balance = $this->Etc_coin_model->get_sum_balance();
            if($threshold > $sum_balance){
                exit("总资产未达到提现阔值:{$sum_balance}\r\n");
            }
            $complete = 0;
            $res = $this->Etc_coin_model->get_withdraw_sys(0.0005);
            $to = $this->config->item('ETCwithdrawTo');
            
            foreach($res as $item) {
                
                $data['cid'] = $item['id'];
                $data['address'] = $item['address'];
                $balance = $this->get_balance($item['address']);
                $value = calculate($balance, strval(0.0005), 'bcsub');
                if($value<=0) { 
                    continue;
                }
                $check_duplicate = $this->Etc_coin_model->get_withdraw_sys_confirm($item['id']);
                if(is_array($check_duplicate)&&count($check_duplicate)>0) {
                    continue;
                }
                $from = decrypt($item['private_key']);
                if(empty($from)) {
                    echo "解码私钥错误:".$item['private_key']."\r\n";
                    continue;
                }
                $result = $this->gen_tx($value,$from,$to);
                if(empty($result)) {
                    continue;
                }
                $data['tx_hash'] = $result;
                if($result === NULL) {
                    echo "系统提现交易失败,时间:".date('Y-m-d H:i:s')."\r\n";
                } else {
                    $data['coin_id'] = $this->coin_id;
                    $data['status'] = 210;
                    $data['amount'] = $item['balance'];
                    $data['to_address'] = $to;
                    $data['create_time'] = time();
                    $this->Etc_coin_model->insert_withdraw_sys_log($data);
                    $complete += 1;
                }
            }
            echo "Complete times:".$complete." :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Check Withdraw
        public function withdrawSysConfirm() {
            $res = $this->Etc_coin_model->get_withdraw_sys_confirm();
            $complete = 0;
            foreach($res as $item) {
                $id = $item['id'];
                $method  = "eth_getTransactionByHash";
                $params  = [$item['tx_hash']];
                $result = $this->call($method,$params);
                if( $result == null || $result['blockNumber'] == null) {
                    var_dump($result);
                    echo "tx hash : ".$item['tx_hash']."\r\n";
                    echo "交易等待广播中...\r\n";
                    continue;
                } else if(isset($result['blockNumber']) && hexdec($result['blockNumber']) >0) {
                    $data['status'] = 280;
                    // update cold wallet
                    $fee = 0.00042;
                    $tmp_value = hexdec($result['value'])/(1.0E+18);
                //    $value = number_format($tmp_value,getFloatLength($tmp_value));
                    $floatLength = intval(getFloatLength($tmp_value));
                    $value = sprintf("%.{$floatLength}f",floatval($tmp_value));
                    $cost = calculate($value, strval($fee), 'bcadd');
                //    $cold['balance'] = calculate($item['amount'], $cost, 'bcsub');
                    $cold['balance'] = $this->get_balance($item['address']);
                    echo "original_balance:".$item['amount']."\r\n";
                    echo "cold_balance:".$cold['balance']."\r\n";
                    $this->Etc_coin_model->update_cold_wallet($cold,$item['cid']);
                    $this->Etc_coin_model->update_sys_withdraw($data,$id);
                    $complete +=1;
                }
            }
            echo "Complete times:".$complete."  :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function gen_tx($value,$from,$to) {
            if($from != $this->config->item('etcPayAccount')) {
                $this->deleteDir($this->config->item('keystorePath'));
                $from = $this->importKey($from);
            }
            $from = iconv(mb_detect_encoding($from, mb_detect_order(), true), "UTF-8", $from);
            $to = iconv(mb_detect_encoding($to, mb_detect_order(), true), "UTF-8", $to);
            $param = [
                "from"      => $from,
                "to"        => $to,
                "gas"       => 21000,
                "gasPrice"  => 20000000000,
                "value"     => $this->toWei($value)
            ];
            var_dump($param);
            if($this->unlockAccount($from)) {
               $method  = "eth_sendTransaction";
               $params  = [$param];
               $out = $this->call($method,$params);
               return $out;
            } else {
                echo "unlock fail\r\n";
                return null;
            }
        }
        
        private function unlockAccount($from) {
            $method  = "personal_unlockAccount";
            $params  = [$from,$this->config->item('accountPWD'),30];
            $result = $this->call($method,$params);
            if(!empty($result)) {
                return $result;
            } else {
                return false;
            }
        }
        
        private function importKey($privateKey) {
            $method = "personal_importRawKey";
            $params = [$privateKey,$this->config->item('accountPWD')];
            $out = $this->call($method,$params);
            return $out;
        }
        
        private function toWei($value) {
            $float = $value*(1.0E+18);
            return number_format($float,0,'.','');
        }
        
        private function deleteDir($dirPath) {
            if (! is_dir($dirPath)) {
                throw new InvalidArgumentException("$dirPath must be a directory");
            }
            if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
                $dirPath .= '/';
            }
            $files = glob($dirPath . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_dir($file)) {
                    self::deleteDir($file);
                } else {
                     if(!strpos($file,$this->config->item('reserveKeystore'))){
                         unlink($file);
                     }
                }
            }
        }
        
        protected function callRemote($method, $params=NULL) {
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
            $out = commit_curl("https://web3.gastracker.io/",false,1,$odata,null,null);
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
}


