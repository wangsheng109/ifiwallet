<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ada_new extends MY_Controller {

	public function __construct()
        {
                parent::__construct();
                $this->load->model('Ada_coin_model');
                $this->base_url = "https://localhost:8090/api/v1/";
                $this->remote_url = "https://cardanoexplorer.com/api/";
                $this->coin_name = "ada";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('ada_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')."\r\n");
                }
        }
	public function create() {
            $phase = $this->config->item('ada_phase');
            $data = [
                "operation"=>"restore",
                "backupPhrase"=>$phase,
                "assuranceLevel"=>"normal",
                "name"=>$this->config->item('walletName'),
                "spendingPassword"=>$this->config->item('spendingPassword')
                ];
            $output = $this->callApi($this->base_url."wallets",$data);
            var_dump($output);
        }
        
        public function deleteCW() {
            $output = $this->Ada_coin_model->delete_cold_wallet();
            var_dump($output);
        }
        
        public function wlist() {
            $output = commit_curl($this->base_url."wallets",true,0);
            $this->output ->set_content_type('application/json') ->set_output($output);
        }
        
        public function newAccount() {
            $accounts_count = $this->Ada_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足: ".$accounts_count.",".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0; $i<$this->config->item('loopTime'); $i++) {
                $odata = [
                "name"=>"acc".time(),
                "spendingPassword"=>$this->config->item('spendingPassword')
                ];
             
                $output = $this->callApi($this->base_url."wallets/".$this->config->item('wallet_id')."/accounts",$odata);
                $data['account']= $output['data']['index'];
                $data['address'] = $output['data']['addresses'][0]['id'];
                $data['create_time'] = $data['update_time'] = time();
                $this->Ada_coin_model->create_account($data);
            }
           echo "成功创建了 ".$i." 个ADA新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deleteWallet() {
            $output = commit_curl($this->base_url."wallets/".$this->config->item('wallet_id'),true,3,null);
            var_dump($output);
        }
        
        public function accounts() {
            $output = commit_curl($this->base_url."wallets/".$this->config->item('wallet_id')."/accounts?page=1&per_page=20",true,0);
            var_dump($output);
        }
        
        public function account() {
            $output = commit_curl($this->base_url."wallets/".$this->config->item('wallet_id')."/accounts/".$this->config->item('accountIndex'),true,0);
            var_dump($output);
        }
        
        public function list_tx() {
            
            $data = [
                  "wallet_id" => $this->config->item('wallet_id'),
               	  "account_index" => "3244823918",
              	 "created_at"    =>  "GT[2018-06-06T12:58:27.145333]"
                ];
            $output = commit_curl($this->base_url."transactions?". http_build_query($data),true,0);
            var_dump($output);
        }
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            $res = $this->Ada_coin_model->get_cold_wallet();
            if(empty($res)){
                exit("未查询到充值记录:".date('Y-m-d H:i:s')."\r\n");
            }
            foreach($res as $item) {
                $log_db_data['uid'] = $item['uid'];
                $log_db_data['address'] = $item['address'];
                $db_amount = floatval($item['balance']);
                $output = commit_curl($this->remote_url."addresses/summary/".$item['address'],true,0);
                if(!isset(json_decode($output,true)['Right']['caTxList'])){
                    var_dump($output);
                    echo "交易查询异常:".date('Y-m-d H:i:s')."\r\n";
                    continue;
                }
                $latest_time = 0;
                $txs_res = json_decode($output,true)['Right']['caTxList'];
                foreach($txs_res as $tx){
                    if($tx['ctbTimeIssued']<=$item['next_start']){
                        continue; //该交易早于最近收录时间
                    }
                    foreach($tx['ctbOutputs'] as $output) {
                        if(strtolower($output[0]) == strtolower($item['address'])){
                            $value = $output[1]['getCoin']/1000000;
                            $log_db_data['tx_hash'] = $tx['ctbId'];
                            $log_db_data['amount'] = $value;
                            $log_db_data['transfer_time']= $tx['ctbTimeIssued'];
                            $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                            $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                            $this->Ada_coin_model->insert_db("coin_deposit_log",$log_db_data);
                            if($tx['ctbTimeIssued']>$latest_time){
                                $latest_time = $tx['ctbTimeIssued'];
                                $cold_wallet_db['next_start'] = $latest_time;
                            }
                            $db_amount = calculate($db_amount,$value,"bcadd");
                            $cold_wallet_db['balance'] = $db_amount;
                            $this->Ada_coin_model->update_cold_wallet($cold_wallet_db,$item['id']);
                            $complete+=1;
                            break;
                        } else {
                        //    echo "未找到符合条件的交易记录:".$output['address']."\r\n";
                        }
                    }
                }

            }
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n"; 
        }
        public function depositOld() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            $res = $this->Ada_coin_model->get_cold_wallet();
            if(empty($res)){
                exit("未查询到充值记录:".date('Y-m-d H:i:s')."\r\n");
            }
            foreach($res as $item) {
                $log_db_data['uid'] = $item['uid'];
                $log_db_data['address'] = $item['address'];
                $db_amount = floatval($item['balance']);
                $get_tx_data = [
                    "wallet_id" => $this->config->item('wallet_id'),
                    "account_index" => $item['account'],
                    "created_at"    =>  $this->get_tx_timestr($item['update_time']),
                    "sort_by"       =>  "created_at"
                ];
                $output = commit_curl($this->base_url."transactions?". http_build_query($get_tx_data),true,0);
                if(!isset(json_decode($output,true)['data'])){
                    var_dump($output);
                    echo "交易查询异常:".date('Y-m-d H:i:s')."\r\n";
                    continue;
                }
                $latest_time = 0;
                $txs_res = json_decode($output,true)['data'];
                foreach($txs_res as $tx){
                    foreach($tx['outputs'] as $output) {
                        if($output['address'] == $item['address']){
                            $value = $output['amount']/1000000;
                            $log_db_data['tx_hash'] = $tx['id'];
                            $log_db_data['amount'] = $value;
                            $log_db_data['transfer_time']= strtotime($tx['creationTime']);
                            $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                            $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                            $this->Ada_coin_model->insert_db("coin_deposit_log",$log_db_data);
                            if(strtotime($tx['creationTime'])>$latest_time){
                                $latest_time = strtotime($tx['creationTime']);
                                $cold_wallet_db['update_time'] = $latest_time;
                            }
                            $db_amount = calculate($db_amount,$value,"bcadd");
                            $cold_wallet_db['balance'] = $db_amount;
                            $this->Ada_coin_model->update_cold_wallet($cold_wallet_db,$item['id']);
                            $complete+=1;
                            break;
                        } else {
                        //    echo "未找到符合条件的交易记录:".$output['address']."\r\n";
                        }
                    }
                }

            }
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n"; 
        }
        
        private function getBestBlock() {
            $info = commit_curl($this->base_url."node-info",true,0);
            if(isset(json_decode($info,true)['data']['blockchainHeight']['quantity'])){
                return intval(json_decode($info,true)['data']['blockchainHeight']['quantity']);
            } else {
                return 0;
            }
        }
        
        public function depositConfirm() {
            $deposits = $this->Ada_coin_model->get_deposit_log();
            $bestBlock=$this->getBestBlock();
            if($bestBlock==0){
                exit("获取当前最大区块数出错\r\n");
            }
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $out = commit_curl($this->remote_url."txs/summary/".$tx_hash,true,0);
                if(!isset(json_decode($out,true)['Right']['ctsBlockHeight'])){
                    echo "未找到符合条件的交易记录:".$tx_hash."\r\n";
                    continue;
                }
                $txBlock = intval(json_decode($out,true)['Right']['ctsBlockHeight']);
                if ($bestBlock>$txBlock) {
                    $data['confirmations'] = $bestBlock-$txBlock;
                    $data['update_time'] = time();
                    $this->Ada_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }        
        
        private function get_account($id) {
            $out = commit_curl($this->base_url."wallets/".$this->config->item('wallet_id')."/accounts/".$id,true,0);
            $output = json_decode($out,true);
            return $output;
        }
        
        private function get_tx_timestr($utime) {
            
            date_default_timezone_set('UTC');
             $timestamp = $utime;
             $day = date('Y-m-d',$timestamp);
             $time = date('H:i:s',$timestamp);
             return "GT[".$day."T".$time."]";
        }
        
        public function withdraw() {
            $complete = 0;
            $res = $this->Ada_coin_model->get_withdraw_log(200);
            foreach($res as $item) {
                $id = $item['id'];
                $output = $this->gen_tx($item['coin_actual_amount'],$this->config->item('accountIndex'),$item['coin_address']);
                if(empty($output['data']['id'])) {
                    var_dump($output);
                    echo "生成交易异常\r\n";
                    continue;
                } else {
                    $data['tx_hash'] = $output['data']['id'];
                    $data['status'] = 210;
                    $data['update_time'] = time();
                    $complete += 1;
                }
                $this->Ada_coin_model->update_withdraw_log($data,$id);
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Ada_coin_model->get_withdraw_log(210);
            $complete = 0;
            $bestBlock=$this->getBestBlock();
            if($bestBlock==0){
                exit("获取当前最大区块数出错\r\n");
            }
            foreach($res as $item) {
                $id = $item['id'];
                $tx_hash = $item['tx_hash'];
                $out = commit_curl($this->remote_url."txs/summary/".$tx_hash,true,0);
                if(!isset(json_decode($out,true)['Right']['ctsBlockHeight'])){
                    echo "未找到符合条件的交易记录:".$tx_hash."\r\n";
                    continue;
                }
                $txBlock = intval(json_decode($out,true)['Right']['ctsBlockHeight']);
                if ($bestBlock-$txBlock>6) {
                    $data['status'] = 280;
                    $data['update_time'] = time();
                    $complete += 1;
                    $this->Ada_coin_model->update_withdraw_log($data,$id);
                    echo $tx_hash." 交易状态更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Withdraw Money to the platform wallet
        public function withdrawSys() {
            $complete = 0;
            $threshold = $this->Ada_coin_model->get_threshold();
            $sum_balance = $this->Ada_coin_model->get_sum_balance();
            if($threshold > $sum_balance){
                exit("总资产未达到提现阔值:{$sum_balance}\r\n");
            }
            $res = $this->Ada_coin_model->get_withdraw_sys(0.2);
            $to = $this->config->item('adaWithdrawTo');
            
            foreach($res as $item) {
                $data['cid'] = $item['id'];
                $data['address'] = $item['address'];
                if(!isset($this->get_account($item['account'])['data']['amount'])) {
                    echo "无法获取账号余额:".$item['account']."\r\n";
                    continue;
                }
                $value = calculate($this->get_account($item['account'])['data']['amount']/1000000,0.2,"bcsub");
                if($value<0) {
                    echo "余额不足\r\n";
                    continue;
                }
                $check_duplicate = $this->Ada_coin_model->get_withdraw_sys_confirm($item['id']);
                if(is_array($check_duplicate)&&count($check_duplicate)>0) {
                    continue;
                }
                $output = $this->gen_tx($value,intval($item['account']),$this->config->item('adaWithdrawTo'));
                if(!isset($output['data'])||!isset($output['data']['id'])) {
                    var_dump($output);
                    echo "生成交易异常\r\n";
                    continue;
                }
                
                if(empty($output['data']['id'])||!isset($output['data']['id'])) {
                    $data['status'] = 400;
                    $data['amount'] = $item['balance'];
                    $data['to_address'] = $to;
                } else {
                    $data['tx_hash'] = $output['data']['id'];
                    $data['status'] = 210;
                    $data['amount'] = $item['balance'];
                    $data['to_address'] = $to;
                }
                $data['create_time'] = time();
                $this->Ada_coin_model->insert_withdraw_sys_log($data);
                $complete += 1;
            }
            echo "Complete times:".$complete." :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Confirm sys Withdraw
        public function withdrawSysConfirm() {
            $res = $this->Ada_coin_model->get_withdraw_sys_confirm();
            $complete = 0;
            $bestBlock=$this->getBestBlock();
            if($bestBlock==0){
                exit("获取当前最大区块数出错\r\n");
            }
            foreach($res as $item) {
                $id = $item['id'];
                $tx_hash = $item['tx_hash'];
                $out = commit_curl($this->remote_url."txs/summary/".$tx_hash,true,0);
                if(!isset(json_decode($out,true)['Right']['ctsBlockHeight'])){
                    echo "未找到符合条件的交易记录:".$tx_hash."\r\n";
                    continue;
                }
                $txBlock = intval(json_decode($out,true)['Right']['ctsBlockHeight']);
                if($bestBlock-$txBlock > 6) {
                    $data['status'] = 280;
                    $count['complete'] += 1;
                    // update cold wallet
                    $outputs = json_decode($out,true)['Right']['ctsOutputs'];
                    $inputs = json_decode($out,true)['Right']['ctsInputs'];
                    $total_outputs = $total_inputs = 0;
                    $transfer_value = 0;
                    foreach($inputs as $input) {
                        $total_inputs += floatval($input[1]['getCoin']/1000000);
                    }
                    foreach($outputs as $output) {
                        $total_outputs += floatval($output[1]['getCoin']/1000000);
                        if(strtolower($output[0]) == strtolower($tx['to_address'])) {
                            $transfer_value = floatval($output[1]['getCoin']/1000000);
                        }
                    }
                    $fee = $total_inputs - $total_outputs;
                    $final_amount = $item['amount'] - $transfer_value - $fee;
                    $cold['balance'] = $final_amount;
                    $this->Ada_coin_model->update_cold_wallet($cold,$item['cid']);
                    $this->Ada_coin_model->update_sys_withdraw($data,$id);
                    $complete +=1;
                } 
            }
            echo "Complete times:".$complete." :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function test_tx() {
            $out = $this->gen_tx($this->config->item('test_amount'),$this->config->item('accountIndex'),$this->config->item('test_address'));
            var_dump($out);
        }
        
        
        private function gen_tx($amount,$accountIndex,$address) {
            
            $des = ["amount"=> $amount*1000000,"address"=>$address];
            $destinations[0] = $des;
            $src = ["accountIndex"=>$accountIndex,"walletId"=>$this->config->item('wallet_id')];
            $data = [
                "groupingPolicy" => null,
                "destinations"  => $destinations,
                "source"        => $src,
                "spendingPassword"=>$this->config->item('spendingPassword')
            ];
            $output = $this->callApi($this->base_url."transactions",$data);
            return $output;
        }
        
}


