<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Btm extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Btm_coin_model');
                $this->base_url = "http://localhost:9888/";
                $this->coin_name = "btm";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][1]['id'];
                $this->coin_id = $ids['coin_tokens'][1]['coin_id'];
                if($this->coin_id!=$this->config->item('btm_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')." \r\n");
                }
                //是否正在进行系统提现
                $this->isSysWithdrawing = false;
        }
        public function list_accounts() {
            $result = $this->callApi($this->base_url."list-accounts",[]);
            var_dump($result);
        }
        
        public function list_balances() {
            $result = $this->callApi($this->base_url."list-balances",["account_id"=>"0IA2I9JAG0A22"]);
            var_dump($result);
        }
        public function testAccount() {
            $alias = "btm".(time()+rand(0,100000));
            $key_params = [
                        "alias" => $alias,
                        "password" => $this->config->item('btmPWD') 
                    ];
            var_dump($key_params);
            $key_result = $this->callApi($this->base_url."create-key",$key_params);
            var_dump($key_result);
        }
        
        public function newHotAccount() {
            
                $alias = "btm".(time()+1000000+rand(0,100000));
                //create key
                $key_params = [
                        "alias" => $alias,
                        "password" => $this->config->item('btmPWD') 
                    ];
                $key_result = $this->callApi($this->base_url."create-key",$key_params);
                $data['account'] = $alias;
                $data['ks_path'] = $key_result['data']['file'];
                $data['private_key'] = file_get_contents($data['ks_path']);
                $data['pub_key'] = $key_result['data']['xpub'];
                // create account
               $root_xpubs = [$data['pub_key']];
                $account_params = [
                        "root_xpubs" => $root_xpubs,
                        "alias" => $alias,
                        "quorum" => 1
                    ];
                $account_result = $this->callApi($this->base_url."create-account",$account_params);
                $data['account_id'] = $account_result['data']['id'];
                // create address
                $address_params = [
                        "account_alias" => $alias,
                        "account_id" => $alias
                    ];
                $address_result = $this->callApi($this->base_url."create-account-receiver",$address_params);
                $data['address'] = $address_result['data']['address'];
                $data['create_time'] = $data['update_time'] = time();
                var_dump($data);
        }
        
	public function newAccount() {
            $accounts_count = $this->Btm_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $alias = "btm".(time()+$i*1000000+rand(0,100000));
                //create key
                $key_params = [
                        "alias" => $alias,
                        "password" => $this->config->item('btmPWD') 
                    ];
                $key_result = $this->callApi($this->base_url."create-key",$key_params);
                $data['account'] = $alias;
                $data['ks_path'] = $key_result['data']['file'];
                $data['private_key'] = file_get_contents($data['ks_path']);
                $pub_key = $key_result['data']['xpub'];
                // create account
               $root_xpubs = [$pub_key];
                $account_params = [
                        "root_xpubs" => $root_xpubs,
                        "alias" => $alias,
                        "quorum" => 1
                    ];
                $account_result = $this->callApi($this->base_url."create-account",$account_params);
                $data['account_id'] = $account_result['data']['id'];
                // create address
                $address_params = [
                        "account_alias" => $alias,
                        "account_id" => $alias
                    ];
                $address_result = $this->callApi($this->base_url."create-account-receiver",$address_params);
                $data['address'] = $address_result['data']['address'];
                $data['create_time'] = $data['update_time'] = time();
                $res = $this->Btm_coin_model->create_account($data);
            }
            if(!$this->isSysWithdrawing){
                $this->deleteDir($this->config->item('btmKeystorePath'));
            }
            echo "成功创建了 ".$i." 个BTM新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            $res = $this->Btm_coin_model->get_cold_wallet();
            foreach($res as $item) {
                $db_amount = floatval($item['balance']);
                $log_db_data['address'] = $item['address'];
                $log_db_data['uid'] = $item['uid'];
                // list transactions
                $txs_params = [
                        "account_id" => $item['account_id'],
                        "from"       => intval($item['next_start']),
                        "count"      =>  5
                ];
                $txs_result = $this->callApi($this->base_url."list-transactions",$txs_params);
                if(!isset($txs_result['data'])){
                    var_dump($txs_result);
                    exit;
                }
                $txs = $txs_result['data'];
                foreach($txs as $tx) {
                    $outputs = $tx['outputs'];
                    $duplicate = $this->Btm_coin_model->check_deposit_log_duplicate($tx['tx_id'],$item['address']);
                    if($duplicate>0){
                        echo "交易-地址对重复:".$tx['tx_id']."-".$item['address']."\r\n";
                        continue;
                    }
                    foreach($outputs as $output){
                        if(isset($output['account_id'])&&$output['account_id']==$item['account_id'] && $output['asset_alias']=="BTM"){
                            $log_db_data['tx_hash'] = $tx['tx_id'];
                            $log_db_data['transfer_time'] = $tx['block_time'];
                            $log_db_data['amount'] = floatval($output['amount']/100000000);
                            $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                            $this->Btm_coin_model->insert_db("coin_deposit_log",$log_db_data);
                            $db_amount = calculate($db_amount,$log_db_data['amount'],"bcadd");
                            $complete +=1;
                        }
                    }
                }
                $cold_wallet_db['next_start'] = intval($item['next_start'])+count($txs);
                $cold_wallet_db['balance'] = $db_amount;
                $this->Btm_coin_model->update_cold_wallet($cold_wallet_db,$item['id']);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function depositConfirm() {
            // get best block number
            $info_result = $this->callApi($this->base_url."wallet-info",[]);
            if(!isset($info_result['data']['best_block_height'])){
                exit("获取当前区块数错误! \r\n");
            }
            $best_block = $info_result['data']['best_block_height'];
            $deposits = $this->Btm_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $tx_params = [
                        "tx_id" => $tx_hash
                ];
                $tx = $this->callApi($this->base_url."get-transaction",$tx_params);
                if (empty($tx)||!isset($tx['data'])) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['data']['block_height']) && $best_block - intval($tx['data']['block_height']) >0) {
                    $data['confirmations'] = $best_block - intval($tx['data']['block_height']);
                    $data['update_time'] = time();
                    $this->Btm_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }
        
        public function withdraw() {
            // get hot wallet balance
            $hw_balance = $this->getAccountBalance($this->config->item('btmPayAccountId'));
            // get withdraw log list
            $token_id = $this->token_id;
            $res = $this->Btm_coin_model->get_withdraw_log(200,$token_id);
            $total_spent = 0;
            $actions[0] = [
                "account_id" => $this->config->item('btmPayAccountId'),
                "amount"    =>  $hw_balance,
                "asset_id"   => "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
                "type"       => "spend_account"
            ];
            $i=1;
            if(count($res)==0){
                exit("没有提现".date('Y-m-d H:i:s')."\r\n");
            }
            foreach($res as $item) {
                $actions[$i] = [
                    "amount"    =>  intval($item['coin_actual_amount']*100000000),
                    "asset_id"   => "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
                    "address"    =>  $item['coin_address'], 
                    "type"       =>  "control_address"
                ];
                $total_spent += intval($item['coin_actual_amount']*100000000);
                $i++;
            }
            if($hw_balance-$total_spent<intval($this->config->item('btmEstimateFee'))) {
                exit("热钱包余额不足1");
            }
            $actions[$i] = [
                    "amount"    =>  $hw_balance-$total_spent-intval($this->config->item('btmEstimateFee')),
                    "asset_id"   => "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
                    "address"    =>  $item['coin_address'], 
                    "type"       =>  "control_address"
            ];
            $total_spent = $hw_balance-intval($this->config->item('btmEstimateFee'));
            $tx_params =[
                "base_transaction"  =>  null,
                "ttl"               =>  0,
                "time_range"        =>  time()+30,
                "actions"           =>  $actions
            ];
            $tx_result = $this->callApi($this->base_url."build-transaction",$tx_params);
            if(!isset($tx_result['data'])){
                   var_dump($tx_result);
                   exit;
            }
            $estimate_params = [
              "transaction_template" => $tx_result['data']  
            ];
            $estimate_result = $this->callApi($this->base_url."estimate-transaction-gas",$estimate_params);
            if(!isset($estimate_result['data']['total_neu'])){
                var_dump($estimate_result);
                exit;
            }
            $total_fee = $estimate_result['data']['total_neu'];
            if($total_fee>intval($this->config->item('btmEstimateFee'))){
                exit("热钱包余额不足2");
            }
            // sign tx
            if(!isset($tx_result['data'])){
                   var_dump($tx_result);
                   exit;
            }
            $sign_tx_params = [
                "password"  =>  $this->config->item('btmPWD'),
                "transaction"   =>  $tx_result['data']
            ];
            $sign_tx_result = $this->callApi($this->base_url."sign-transaction",$sign_tx_params);
            //submit tx
            if(!isset($sign_tx_result['data']['transaction']['raw_transaction'])){
                   var_dump($sign_tx_result);
                   echo "not contains raw transaction \r\n";
                   exit;
            }
            $submit_tx_params = [
                "raw_transaction"   =>  $sign_tx_result['data']['transaction']['raw_transaction']
            ];
            $tx_data = $this->callApi($this->base_url."submit-transaction",$submit_tx_params);
            $complete = 0;
            if(isset($tx_data['data']['tx_id'])){
                $data['tx_hash'] = $tx_data['data']['tx_id'];
                $data['status'] = 210;
                $data['update_time'] = time();
                $complete += 1;
           } else {
                    var_dump($tx_data);
                    $data['status'] = 200;
                    $data['update_time'] = time();
            }
            foreach($res as $item) {
                $id = $item['id'];
                $this->Btm_coin_model->update_withdraw_log($data,$id);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            // get best block number
            $info_result = $this->callApi($this->base_url."wallet-info",[]);
            if(!isset($info_result['data']['best_block_height'])){
                exit("获取当前区块数错误! \r\n");
            }
            $best_block = $info_result['data']['best_block_height'];
            $res = $this->Btm_coin_model->get_withdraw_log(210);
            $complete = 0;
            foreach($res as $item) {
                $id = $item['id'];
                $tx_hash = $item['tx_hash'];
                $tx_params = [
                        "tx_id" => $tx_hash
                ];
                $tx = $this->callApi($this->base_url."get-transaction",$tx_params);
                if (empty($tx)||!isset($tx['data'])) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['data']['block_height']) && $best_block - intval($tx['data']['block_height']) >0) {
                    $data['status'] = 280;
                    $data['update_time'] = time();
                    $this->Btm_coin_model->update_withdraw_log($data,$id);
                    $complete += 1;
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Withdraw Money to the platform wallet
        public function withdrawSys() {
            $this->isSysWithdrawing = true;
            $threshold = $this->Btm_coin_model->get_threshold();
            $sum_balance = $this->Btm_coin_model->get_sum_balance();
            if($threshold > $sum_balance){
                $this->isSysWithdrawing = false;
                exit("总资产未达到提现阔值:{$sum_balance}\r\n");
            }
            $complete = 0;
            $res = $this->Btm_coin_model->get_withdraw_sys(0);
            $to = $this->config->item('btmWithdrawTo');
            // 构建交易inputs
            $i=0;
            $total_inputs=0;
            $actions = [];
            $inputs_array = [];
            foreach($res as $item) {
                $data['cid'] = $item['id'];
                $data['address'] = $item['address'];
                //是否存在相同的地址处于系统提现未确认状态，若存在则不处理
                $check_duplicate = $this->Btm_coin_model->get_withdraw_sys_confirm($item['id']);
                if(is_array($check_duplicate)&&count($check_duplicate)>0) {
                    echo "忽略重复的 交易-地址 对\r\n";
                    continue;
                }
                //临时生成提现账号的key store
                if($this->createKeyStore($item['ks_path'], $item['private_key'])===false){
                    echo "创建key store失败:".$item['address']."\r\n";
                    continue;
                }
                //被提现地址是否不能是冷热钱包地址账号
                if(strtolower($item['account_id'])==strtolower($this->config->item('btmPayAccountId'))||strtolower($item['address'])==strtolower($this->config->item('btmPayAddress'))){
                    echo "不能从热钱包账号或地址提现\r\n";
                    continue;
                }
                if(strtolower($item['address'])==strtolower($this->config->item('btmWithdrawTo'))) {
                    echo "提现地址不能是冷钱包地址\r\n";
                    continue;
                }
                $actions[$i] =[
                    "account_id" => $item['account_id'],
                    "amount"    =>  $this->getAccountBalance($item['account_id']),
                    "asset_id"   => "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
                    "type"       => "spend_account"
                ];
                $total_inputs += $this->getAccountBalance($item['account_id']);
                $inputs_array[$i] = $item;
                $i++;
            }
            //构建output
            $actions[$i] =   [
                "amount"    =>  $total_inputs-intval($this->config->item("btmEstimateFee")),
                "asset_id"   => "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
                "address"    =>  $to, 
                "type"       =>  "control_address"
            ];
            $tx_data = $this->buildTx($actions);
            if(!isset($tx_data['data']['tx_id'])) {
                echo "系统提现交易失败,时间:".date('Y-m-d H:i:s')."\r\n";
            } else {
                $data['tx_hash'] = $tx_data['data']['tx_id'];
                $data['coin_id'] = $this->coin_id;
                $data['status'] = 210;
                $data['to_address'] = $to;
                $data['create_time'] =  time();
                foreach($inputs_array as $item) {
                    $data['cid']    = $item['id'];
                    $data['address'] = $item['address'];
                    $data['amount'] = $item['balance'];
                    $this->Btm_coin_model->insert_withdraw_sys_log($data);
                }
                $complete += 1;
            }
            echo "Complete times:".$complete." :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Check Withdraw
        public function withdrawSysConfirm() {
            // get best block number
            $info_result = $this->callApi($this->base_url."wallet-info",[]);
            if(!isset($info_result['data']['best_block_height'])){
                exit("获取当前区块数错误! \r\n");
            }
            $best_block = $info_result['data']['best_block_height'];
            // get sys withdraw logs
            $res = $this->Btm_coin_model->get_withdraw_sys_confirm();
            $complete = 0;
            foreach($res as $item) {
                $id = $item['id'];
                $tx_hash = $item['tx_hash'];
                $tx_params = [
                        "tx_id" => $tx_hash
                ];
                $tx = $this->callApi($this->base_url."get-transaction",$tx_params);
                if (empty($tx)||!isset($tx['data'])) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                else if (isset($tx['data']['block_height']) && $best_block - intval($tx['data']['block_height']) >0) {
                    $data['status'] = 280;
                    // update cold wallet
                    $cold['balance'] = 0;
                    $this->Btm_coin_model->update_cold_wallet($cold,$item['cid']);
                    $this->Btm_coin_model->update_sys_withdraw($data,$id);
                    $complete +=1;
                } else {
                    var_dump($tx);
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
            echo "Complete times:".$complete."  :".date('Y-m-d H:i:s')."\r\n";
        }
        
        private function buildTx($actions) {
            
            $tx_params =[
                "base_transaction"  =>  null,
                "ttl"               =>  0,
                "time_range"        =>  time()+30,
                "actions"           =>  $actions
            ];
            $tx_result = $this->callApi($this->base_url."build-transaction",$tx_params);
            if(!isset($tx_result['data'])){
                   var_dump($tx_result);
                   exit;
            }
            $sign_tx_params = [
                "password"  =>  $this->config->item('btmPWD'),
                "transaction"   =>  $tx_result['data']
            ];
            $sign_tx_result = $this->callApi($this->base_url."sign-transaction",$sign_tx_params);
            if(!isset($sign_tx_result['data']['transaction']['raw_transaction'])){
                   var_dump($sign_tx_result);
                   echo "not contains raw transaction \r\n";
                   exit;
            }
            $submit_tx_params = [
                "raw_transaction"   =>  $sign_tx_result['data']['transaction']['raw_transaction']
            ];
            $tx_data = $this->callApi($this->base_url."submit-transaction",$submit_tx_params);
            return $tx_data;
        }
        
        private function getAccountBalance($account_id) {
            $result = $this->callApi($this->base_url."list-balances",[]);
            if(!isset($result['data'])){
                var_dump($result);
                exit("获取余额失败\r\n");
            }
            $balance = 0;
            foreach($result['data'] as $item){
                if(isset($item['account_id']) && $item['account_id']==$account_id && $item['asset_alias']=="BTM"){
                    $balance = $item['amount'];
                    break;
                }
            }
            return $balance;
        }
        
        private function createKeyStore($fileName,$fileData) {
            return file_put_contents($fileName,$fileData);
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
                     if(!strpos($file,$this->config->item('btmReserveKeystore'))){
                         unlink($file);
                     }
                }
            }
        }
}


