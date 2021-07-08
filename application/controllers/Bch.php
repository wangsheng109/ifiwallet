<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bch_new extends MY_Controller {

	public function __construct()
        {
                parent::__construct();
                $this->load->helper('url_helper');
                $this->load->model('Bch_coin_model');
                $this->rpc_url = "http://localhost:".$this->config->item('bchPort');
                $this->rpc_user = $this->config->item('rpcuser');
                $this->rpc_pass = $this->config->item('rpcpass');
                $this->coin_name = "bch";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('bch_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')." \r\n");
                }
        }
        
	private function unlockWallet() {
            $params = [$this->config->item('bchPWD'),30];
            $out = $this->call("walletpassphrase",$params);
            var_dump($out);
        }
        
        private function lockWallet() {
            $out = $this->call("walletlock",[]);
            var_dump($out);
        }
        
        private function get_balance($address) {
            $params =[
                    1,
                    9999999,
                    is_array($address)?$address:[$address]
                ];
            $unspents = $this->call("listunspent",$params);
            if(empty($unspents)) {
                return 0;
            }
            $balance = 0;
            foreach($unspents as $unspent) {
                $tmp_amount = number_format($unspent['amount'], getFloatLength($unspent['amount']), '.', '');
                $max_float = getFloatLength($balance)>getFloatLength($unspent['amount'])? getFloatLength($balance):getFloatLength($unspent['amount']);
                $balance = bcadd($balance, $tmp_amount, $max_float);
            }
            return $balance;
        }
        
        public function info() {
            $out = $this->call("getinfo",[]);
            var_dump($out);
        }
        
        public function newAccount() {
            $accounts_count = $this->Bch_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            $this->unlockWallet();
            for($i=0; $i<$this->config->item('loopTime'); $i++) {
                $account_name = "bch".time().$i;
                $params = [$account_name];
                $address = $this->call("getaccountaddress",$params);
                if(empty($address)){
                    echo "账户为空 \r\n";
                    continue;
                }
                $data['account']= $account_name;
                $data['address'] = substr($address,strpos($address,":")+1);
                $data['create_time'] = $data['update_time'] = time();
                $this->Bch_coin_model->create_account($data);
            }
           $this->lockWallet();
           echo "成功创建了 ".$i." 个BCH新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = $drop = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            $res = $this->Bch_coin_model->get_cold_wallet();
            foreach($res as $item) {
                $db_amount = floatval($item['balance']);
                $real_amount = $this->get_balance($item['address']);
                
                $to_post = array();
                $txs = $this->call("listtransactions",[$item['account']]);
                if(empty($txs)||!is_array($txs)){
                    var_dump($txs);
                    echo "该账号没有交易:".$item['account']."\r\n";
                    continue;
                }
             // wrap content
                $log_db_data['address'] = $item['address'];
                $log_db_data['uid'] = $item['uid'];
                $latest_time = 0;
                foreach($txs as $tx) {
                    if ($tx['category'] != 'receive'){
                        echo "不是收款方交易\r\n";
                        continue;
                    }
                    if ($tx['timereceived'] <= $item['update_time']){
                        echo "小于utime\r\n";
                        continue;
                    }
                    if(intval($tx["timereceived"])>$latest_time){
                        $latest_time = intval($tx["timereceived"]);
                        $cold_wallet_db['update_time'] = $latest_time;
                    }
                    $log_db_data['amount']= $tx['amount'];
                    $log_db_data['tx_hash'] =  $tx["txid"];
                    $log_db_data['transfer_time'] =  $tx["timereceived"];
                    $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                    $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                    $this->Bch_coin_model->insert_db("coin_deposit_log",$log_db_data);
                    $db_amount = calculate($db_amount,$tx['amount'],"bcadd");
                    $cold_wallet_db['balance'] = $db_amount;
                    $this->Bch_coin_model->update_cold_wallet($cold_wallet_db,$item['id']);
                    $complete +=1;
                }
            }
            echo "Complete times:".$complete.", Drop times:".$drop.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function depositConfirm() {
            $deposits = $this->Bch_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $tx = $this->call("gettransaction",[$item['tx_hash']]);
                if (empty($tx)) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['confirmations']) && $tx['confirmations'] > 0) {
                    $data['confirmations'] = $tx['confirmations'];
                    $this->Bch_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }
        
        public function withdraw() {
            $complete = 0;
            $res = $this->Bch_coin_model->get_withdraw_log(200);
            if (empty($res)) exit("暂无提现\r\n");
            // 获取热钱包的unspent和计算热钱包余额
            $accounts = $this->call("getaddressesbyaccount",[$this->config->item('bchPayAccount')]);
            $unspents = $this->call("listunspent",[1,999999,$accounts]);
            if (empty($unspents)) {
                exit("热钱包交易费用不足!\r\n");
            }
            $hw_balance = 0; // 热钱包btc余额
            $remain_balance = 0; // 热钱包剩余的余额
            $filter_unspents = []; // 过滤后的unspent
            $send_array = []; // 发送名单
            $withdrawed_array = []; // 存放withdraw_list中成功进入send_array的数据
            $input_length = count($unspents); // input长度
            $output_length = count($res) + 1; // output长度，+1代表剩余的余额作为一个output返还到热钱包
            foreach ($unspents as $unspent) {
                $hw_balance = calculate($hw_balance, $unspent['amount'], 'bcadd');
                $filter_unspents[] = array(
                    'txid' => $unspent['txid'],
                    'vout' => $unspent['vout']
                );
            }
            $fee = calculateFee($input_length, $output_length);
            $remain_balance = calculate($hw_balance, $fee, 'bcsub');
            if ($remain_balance < 0) {
                exit("热钱包余额不足!\r\n");
            }
            foreach($res as $item) {
                $id = $item['id'];
                //校验地址
                $validate = $this->call("validateaddress",[$item['coin_address']]);
                if ($validate['isvalid'] == false) {
                    $data['status'] = 400;
                    $data['update_time'] = time();
                    echo "转出地址有误!\r\n";
                    $this->Bch_coin_model->update_withdraw_log($data,$id);
                    continue;
                } 
                $remain_balance = calculate($remain_balance, $item['coin_actual_amount'], 'bcsub');
                if ($remain_balance < 0) {
                    echo "热钱包余额不足!\r\n";
                    continue;
                }
                if (isset($send_array[$item['coin_address']])) {
                    $send_array[$item['coin_address']] = calculate($send_array[$item['coin_address']], $item['coin_actual_amount'], 'bcadd');
                } else {
                    $send_array[$item['coin_address']] = $item['coin_actual_amount'];
                }
                $withdrawed_array[] = $item;
            }
            try{
                if (! empty($send_array)) {
                    $send_array[$this->config->item('bchPayAddress')] = $remain_balance;
                    $tx_base = $this->call("createrawtransaction",[$filter_unspents, $send_array]);
                    $this->unlockWallet();
                    $sign_hex = $this->call("signrawtransaction",[$tx_base]);
                    if(!$this->call("settxfee",[$this->config->item('FEE_PER_KILOBYTE')])){
                            exit("交易费设置异常!\r\n");
                    }
                    $tx_hash = $this->call("sendrawtransaction",[$sign_hex['hex']]);
                    $this->lockWallet();
                    if (empty($tx_hash)) {
                        throw new Exception('发送失败');
                    }
                    foreach ($withdrawed_array as $item) {
                        echo "{$item['withdraw_number']}:{$tx_hash}\r\n"; 
                        $data['tx_hash'] = $tx_hash;
                        $data['status'] = 210;
                        $data['update_time'] = time();
                        $this->Bch_coin_model->update_withdraw_log($data,$item['id']);
                        $complete += 1;
                    }
                } else {
                    echo "send_array 为空! \r\n";
                }
            } catch (Exception $e) {
                $this->lockWallet();
                echo $e->getMessage() . "\r\n";
                if (!empty($withdrawed_array)) {
                    foreach ($withdrawed_array as $item) {
                        echo "{$item['withdraw_number']}:{$tx_hash}\r\n"; 
                        //防止网络抖动造成的误判
//                        $data['status'] = 400;
//                        $data['update_time'] = time();
//                        $this->Bch_coin_model->update_withdraw_log($data,$item['id']);
                    }
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Bch_coin_model->get_withdraw_log(210);
            $complete = 0;
            foreach($res as $item) {
                $id = $item['id'];
                $validation = $this->call("validateaddress",[$item['coin_address']]);
                if ($validation['isvalid'] == false) {
                    echo "非法地址!\r\n";
                    continue;
                }
                if (empty($item['tx_hash'])) {
                    echo "查询transaction异常!\r\n";
                    continue;
                } else {
                    $tx = $this->call("gettransaction",[$item['tx_hash']]);
                    if (empty($tx)) {
                        echo "交易等待广播中...\r\n";
                        continue;
                    }
                    if ($tx['confirmations'] > 0) {
                        $data['status'] = 280;
                        $this->Bch_coin_model->update_withdraw_log($data,$id);
                        $complete +=1;
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
            $complete = 0;
            $send_amount = 0; // 最终发送金额
            $filter_unspents = []; // 过滤后的unspent
            $send_array = []; // 发送名单
            $withdrawed_array = []; // 需写入日志的地址
            $input_length = 0; // input长度
            $output_length = 1; // output长度
            $unspents = $this->call("listunspent",[6, 999999]);
            if (empty($unspents)) {
                exit("暂无记录\r\n");
            }
            foreach ($unspents as $unspent) {
                $unspent['address'] = substr($unspent['address'],strpos($unspent['address'],":")+1);
                if (! isset($unspent['account']) || empty($unspent['account']) || $unspent['account'] == $this->config->item('bchPayAccount')  || in_array(strtolower(strval($unspent['address'])), [strtolower(strval($this->config->item('bchPayAddress'))), strtolower(strval($this->config->item('bchWithdrawTo')))])){
                    var_dump($unspent);
                    echo "payAddress:".$this->config->item('bchPayAddress')."\r\n";
                    echo "withdrawTo:".$this->config->item('bchWithdrawTo')."\r\n";
                    echo "系统提现账号有误\r\n";
                    continue;
                }
                $count = $this->Bch_coin_model->check_deposit_log_duplicate($unspent['txid'],$unspent['address']);
                
                if(empty($count)) {
                    echo "没有在数据库中找到unspent\r\n";
                 //   continue;
                }
                $filter_unspents[] = array(
                    'txid' => $unspent['txid'],
                    'vout' => $unspent['vout']
                );
                $input_length++;
                $send_amount = calculate($send_amount, $unspent['amount'], 'bcadd');
                if (isset($withdrawed_array[$unspent['address']])) {
                    $withdrawed_array[$unspent['address']] = calculate($withdrawed_array[$unspent['address']], $unspent['amount'], 'bcadd');
                } else {
                    $withdrawed_array[$unspent['address']] = numberFormat($unspent['amount']);
                }
            }
            try {
                if(!empty($withdrawed_array)){
                    $threshold = $this->Bch_coin_model->get_threshold();
                    $fee = calculateFee($input_length, $output_length);
                    $remain_balance = calculate($send_amount, $fee, 'bcsub');
                    if ($remain_balance < 0) {
                        echo "费用不足!\r\n";exit;
                    }
                    if (calculate($remain_balance, $threshold, 'bcsub') < 0) {
                        exit("总资产未达到提现阔值:{$remain_balance}\r\n");
                    }
                    $send_array[$this->config->item("bchWithdrawTo")] = $remain_balance;
                    $tx_base = $this->call("createrawtransaction",[$filter_unspents, $send_array]);
                    $this->unlockWallet();
                    $sign_hex = $this->call("signrawtransaction",[$tx_base]);
                    if(!$this->call("settxfee",[$this->config->item('FEE_PER_KILOBYTE')])){
                            exit("交易费设置异常!\r\n");
                    }
                    $tx_hash = $this->call("sendrawtransaction",[$sign_hex['hex']]);
                    $this->lockWallet();
                    if (!empty($tx_hash)) {
                        echo "{$tx_hash}\r\n";
                        foreach ($withdrawed_array as $address => $amount) {
                            $cold_wallet = $this->Bch_coin_model->get_cold_wallet(['address'=>$address],true);
                            if(empty($cold_wallet)||!isset($cold_wallet[0])) {
                                continue;
                            }
                            $data['cid'] = $cold_wallet[0]['id'];
                            $data['coin_id'] = $this->config->item('bch_coin_id');
                            $data['address'] = $address;
                            $data['tx_hash'] = $tx_hash;
                            $data['status'] = 210;
                            $data['create_time'] = time();
                            $data['to_address'] = $this->config->item('bchWithdrawTo');
                            $data['amount'] = $amount;
                            $this->Bch_coin_model->insert_withdraw_sys_log($data);
                            $complete += 1;
                        }
                    }
                }
            } catch (Exception $e) {
                $this->lockWallet();
                echo $e->getMessage() . "\r\n";
            }
            echo "Complete times:".$complete." :".date('Y-m-d H:i:s')."\r\n";
        }
        
        //Confirm sys Withdraw
        public function withdrawSysConfirm() {
            try{
                $res = $this->Bch_coin_model->get_withdraw_sys_confirm();
                $send_fee_array = array(); 
                $update_array = [];
                $complete=0;
                foreach($res as $item) {
                    $cold_wallet = $this->Bch_coin_model->get_cold_wallet(['address'=>$item['address']],true);
                    if(empty($cold_wallet)||!isset($cold_wallet[0])) {
                        continue;
                    }
                    $id = $item['id'];
                    $tx = $this->call("gettransaction",[$item['tx_hash']]);
                    if (empty($tx)){
                        continue;
                    }
                    if ($tx['confirmations'] < 1){
                        continue;
                    }
                    $wallet_balance = isset($update_array[$item['address']]) ? $update_array[$item['address']] : $cold_wallet[0]['balance'];
                    $update_array[$item['address']] = $balance = calculate($wallet_balance, $item['amount'], 'bcsub');
                    $cold['balance'] = $balance;
                    $this->Bch_coin_model->update_cold_wallet($cold,$item['cid']);
                    $sys_log_data['status'] = 280;
                    $this->Bch_coin_model->update_sys_withdraw($sys_log_data,$id);
                    $complete++;
                }
                echo 'Complete time: ' . date('Y-m-d H:i:s') . ', Complete count:' . $complete . "\r\n";
            } catch (Exception $e) {
                echo $e->getMessage() . "\r\n";
            }
        }
        
}


