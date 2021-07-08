<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Zen extends MY_Controller {

	public function __construct()
        {
                parent::__construct();
                $this->load->helper('url_helper');
                $this->load->model('Zen_coin_model');
                $this->rpc_url = "http://localhost:".$this->config->item('zenPort');
                $this->rpc_user = $this->config->item('rpcuser');
                $this->rpc_pass = $this->config->item('rpcpass');
                $this->coin_name = "zen";
                $ids = $this->get_coin_ids($this->coin_name);
                $this->chain_id = $ids['chain_id'];
                $this->token_id = $ids['coin_tokens'][0]['id'];
                $this->coin_id = $ids['coin_tokens'][0]['coin_id'];
                if($this->coin_id!=$this->config->item('zen_coin_id')){
                    exit("coin id 数据库与配置文件不一致! ".date('Y-m-d H:i:s')." \r\n");
                }
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
            $accounts_count = $this->Zen_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            for($i=0; $i<$this->config->item('loopTime'); $i++) {
                $address = $this->call("getnewaddress",[""]);
                if(empty($address)){
                    echo "账户为空 \r\n";
                    continue;
                }
                $data['address'] = $address;
                $data['create_time'] = $data['update_time'] = time();
                $this->Zen_coin_model->create_account($data);
            }
           echo "成功创建了 ".$i." 个ZEN新账号:".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function deposit() {
            $complete = 0;
            $log_db_data = array();
            $log_db_data['chain_id'] = $this->chain_id;
            $log_db_data['token_id'] = $this->token_id;
            $log_db_data['coin_id'] = $this->coin_id;
            $log_db_data['coin_name'] = $this->coin_name;
            //get next_start_squence and account_name for zencash
            $chain_data = $this->Zen_coin_model->get_chain_data($this->coin_name);
            $next_start = $chain_data->next_start_sequence;
            $get_payments_params = [
                "",
                1000,
                0
            ];
            $payments = $this->call("listtransactions",$get_payments_params);
            if(count($payments)<=0){
                exit("List payments error or no deposit: ".date('Y-m-d H:i:s')." \r\n");
            }
            for($i=count($payments);$i>0;$i--) {
                $item = $payments[$i-1];
                if(intval($item['time'])==intval($next_start)){
                     echo "达到上一个交易时间：".$item['time']."\r\n";
                     echo "tx: {$item['txid']}\r\n";
                     break;
                 }
                if($item['account']!=""){
                    echo "to : ".$item['account']."\r\n";
                    echo "不是充值账户号\r\n";
                    continue;
                }
                if ($item['category'] != 'receive'){
                        echo "不是收款方交易".$item['address']."\r\n";
                        echo "tx: {$item['txid']}\r\n";
                        continue;
                 }
                //retrieve info such as balance from cold wallet by the address(memo) and coin_id
                $cold_wallet_array = $this->Zen_coin_model->get_cold_wallet(['address'=>$item['address']],true);
                if(!isset($cold_wallet_array[0])) {
                    echo "address : ".$item['address']."\r\n";
                    echo "地址不在冷钱包数据库里面\r\n";
                    continue;
                }
                $cold_wallet = $cold_wallet_array[0];
                $value = $item['amount'];
                
                $log_db_data['uid'] = $cold_wallet['uid'];
                $log_db_data['address'] = $cold_wallet['address'];
                $log_db_data['amount'] = $value;
                $log_db_data['tx_hash'] =  $item['txid'];
                $log_db_data['transfer_time'] = intval($item['timereceived']);
                $log_db_data['confirmations'] = 0;
                $log_db_data['create_time'] = $log_db_data['update_time'] = time();
                $log_db_data['deposit_number'] = "RC".(time()+rand(0,100000));
                $db_amount = floatval($cold_wallet['balance']);
                $db_amount = calculate($db_amount,$value,"bcadd");
                $this->Zen_coin_model->insert_db("coin_deposit_log",$log_db_data);
                $cold_wallet_db['balance'] = $db_amount;
                $this->Zen_coin_model->update_cold_wallet($cold_wallet_db,$cold_wallet['id']);
                $complete +=1;
            }
            $new_next_start = isset($payments[count($payments)-1]['time'])?intval($payments[count($payments)-1]['time']):$next_start;
            $this->Zen_coin_model->update_chain_data(["next_start_sequence"=>$new_next_start],$this->coin_name); 
            echo "Complete count:".$complete."***Comeplete time:".date('Y-m-d H:i:s')."\r\n";
        }
        public function getDepositLog($txHash) {
            $result = $this->Zen_coin_model->get_full_deposit($txHash);
            var_dump($result);
        }
        public function depositConfirm() {
            $deposits = $this->Zen_coin_model->get_deposit_log();
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $tx = $this->call("gettransaction",[$item['tx_hash']]);
                if (empty($tx)) {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
                if (isset($tx['confirmations']) && $tx['confirmations'] > 0) {
                    $data['confirmations'] = $tx['confirmations'];
                    $this->Zen_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认数更新\r\n";
                } else {
                    echo $tx_hash." 交易等待广播中...\r\n";
                    continue;
                }
            }
        }
        
        public function withdraw() {
            $complete = 0;
            $res = $this->Zen_coin_model->get_withdraw_log(200);
            
            // 获取热钱包的unspent和计算热钱包余额
            $accounts = $this->call("getaddressesbyaccount",[$this->config->item('zenPayAccount')]);
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
             echo "热钱包余额:".$hw_balance."\r\n";
             if (empty($res)) exit("暂无提现\r\n");
            foreach($res as $item) {
                $id = $item['id'];
                //校验地址
                $validate = $this->call("validateaddress",[$item['coin_address']]);
                if ($validate['isvalid'] == false) {
                    $data['status'] = 400;
                    $data['update_time'] = time();
                    echo "转出地址有误!\r\n";
                    $this->Zen_coin_model->update_withdraw_log($data,$id);
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
                    $send_array[$this->config->item('zenPayAddress')] = $remain_balance;
                    $tx_hash = $this->call("sendmany",["",$send_array]);
                    if (empty($tx_hash)) {
                        throw new Exception('发送失败');
                    }
                    foreach ($withdrawed_array as $item) {
                        echo "{$item['withdraw_number']}:{$tx_hash}\r\n"; 
                        $data['tx_hash'] = $tx_hash;
                        $data['status'] = 210;
                        $data['update_time'] = time();
                        $this->Zen_coin_model->update_withdraw_log($data,$item['id']);
                        $complete += 1;
                    }
                } else {
                    echo "send_array 为空! \r\n";
                }
            } catch (Exception $e) {
                echo $e->getMessage() . "\r\n";
                if (!empty($withdrawed_array)) {
                    foreach ($withdrawed_array as $item) {
                        echo "{$item['withdraw_number']}:{$tx_hash}\r\n"; 
                        //防止网络抖动造成误判，手动解决
                        continue;
//                        $data['status'] = 400;
//                        $data['update_time'] = time();
//                        $this->Zen_coin_model->update_withdraw_log($data,$item['id']);
                    }
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }
        
        public function withdrawConfirm() {
            $res = $this->Zen_coin_model->get_withdraw_log(210);
            $complete = 0;
            foreach($res as $item) {
                $id = $item['id'];
                $validation = $this->call("validateaddress",[$item['coin_address']]);
                if ($validation['isvalid'] == false) {
                    echo $item['coin_address']." : 非法地址!\r\n";
                    continue;
                }
                if (empty($item['tx_hash'])) {
                    echo "查询transaction异常!\r\n";
                    continue;
                } else {
                    $tx = $this->call("gettransaction",[$item['tx_hash']]);
                    if (empty($tx)) {
                        echo $item['tx_hash'].": 交易等待广播中...\r\n";
                        continue;
                    }
                    if ($tx['confirmations'] > 0) {
                        $data['status'] = 280;
                        $this->Zen_coin_model->update_withdraw_log($data,$id);
                        echo $item['tx_hash']." : 交易确认数更新成功\r\n";
                        $complete +=1;
                    } else {
                        echo $item['tx_hash']." : 交易等待广播中...\r\n";
                        continue;
                    }
                }
            }
            echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        }

}


