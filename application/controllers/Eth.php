<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Eth extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Eth_coin_model');
                // $this->rpc_url = "http://localhost:".$this->config->item('eth_port');
                $this->rpc_url = $this->config->item('remoteRPC');
                $this->coin_name = "eth";
                $this->coin_id = $this->config->item('eth_coin_id');
                $this->chain_id = $this->config->item('erc20_chain_id');
        }
        
        public function decrypt_tool() {
            echo decrypt($this->config->item('test_private_key'))."\r\n";
        //      echo decrypt($privateKey)."\r\n";
        }
        
        public function encrypt_tool($privateKey) {
            echo encrypt($privateKey)."\r\n";
        }

        public function get_nonce($address) {
            $method  = "eth_getTransactionCount";
            $param = [$address,"latest"];
            $result = $this->call($method,$param);
            if(is_array($result)){
                exit("\r\n error when getting nonce \r\n");
            }
            $count = hexdec($result);
            return $count;
        }

        public function getBestBlock() {
            
            $result = commit_curl($this->config->item('ethApiUrl')."module=proxy&action=eth_blockNumber&apikey=".$this->config->item("etherscanAPIkey"));
            if(empty(json_decode($result,true)['result']))
            {
                return 0;
            }else{
                $block = hexdec(json_decode($result,true)['result']);
                return $block;
            }
        }
        
        public function newAccount() {
            $accounts_count = $this->Eth_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            $n=0;
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $privateKey = bin2hex(openssl_random_pseudo_bytes(32));
                $pkey = $this->importKey($privateKey);
                if(empty($pkey)){
                    continue;
                }
                $data['address'] = $pkey;
                $data['private_key'] = encrypt($privateKey);
                $data['create_time'] = $data['update_time'] = time();
                $this->Eth_coin_model->create_account($data);
                $n++;
            }
            $this->deleteDir($this->config->item('ETHkeystorePath'));
            echo "成功创建了 ".$n." 个ETH新账号:".date('Y-m-d H:i:s')."\r\n";
        }

        public function createAccount() {
            $accounts_count = $this->Eth_coin_model->get_coin_accounts_count();
            if($accounts_count > $this->config->item('minAvailableAccounts')) {
                exit("可用账户充足 ".date('Y-m-d H:i:s')."\r\n");
            }
            $n=0;
            for($i=0;$i<$this->config->item('loopTime');$i++) {
                $privateKey = bin2hex(openssl_random_pseudo_bytes(32));
                $transaction = new Transaction(NULL);
                $pkey = $transaction->privateKeyToAddress($privateKey);
                if(empty($pkey)){
                    continue;
                }
                $data['address'] = $pkey;
                $data['private_key'] = encrypt($privateKey);
                $data['create_time'] = $data['update_time'] = time();
                $this->Eth_coin_model->create_account($data);
                $n++;
            }
            echo "成功创建了 ".$n." 个ETH新账号:".date('Y-m-d H:i:s')."\r\n";
        }

        public function testAccount() {
            $privateKey = bin2hex(openssl_random_pseudo_bytes(32));
            $transaction = new Transaction(NULL);
            $address = $transaction->privateKeyToAddress($privateKey);
            echo "\r\n private key : ".$privateKey." \r\n";
            echo "\r\n address : ".$address."\r\n";
        }

        public function deposit(){
            $bestBlock = $this->getBestBlock();
            if($bestBlock==0){
                exit("Network problem\r\n");
            }
            $ress = $this->Eth_coin_model->get_cold_wallet();
            $all_address = array();
            $address_user = array();
            foreach($ress as $res){
                $address_user[strtolower($res['address'])]=$res['user_id'];
            }
            $futureBlock = 0;
            $coin = $this->Eth_coin_model->get_eth_info();
            foreach($ress as $res){
                $start_block = $coin['next_start'];
                $end_block=intval($start_block)+100;
                $address = $res['address'];
                $url=$this->config->item('ethApiUrl')."module=account&action=txlist&address=".$address."&startblock=".$start_block."&endblock=".$end_block."&sort=asc&apikey=".$this->config->item("etherscanAPIkey");
                $raw_txs_output = commit_curl($url);
                //update coin next start
                $futureBlock=($end_block+1<$bestBlock)?$end_block+1:$bestBlock;
                $this->Eth_coin_model->update_coins(['next_start'=>$futureBlock],$coin['id']);
                if(empty(json_decode($raw_txs_output,true)['result'])){
                    continue;
                }
                $raw_txs = json_decode($raw_txs_output,true)['result'];
                foreach($raw_txs as $raw_tx){
                    $to_address = $raw_tx['to'];
                    if(strtolower($to_address)!==strtolower($address)){
                        continue;
                    }
                    $value=$raw_tx['value'];
                    $log=array();
                    $log['user_id']=$address_user[strtolower($to_address)];
                    $log['chain_id']=$this->chain_id;
                    $log['coin_id']=$coin['id'];
                    $log['address']=$to_address;
                    $log['tx_hash']=$raw_tx['hash'];
                    $log['amount']=$value/1000000000000000000;
                    $log['status']=100;
                    $log['tx_timestamp']=$raw_tx['timeStamp'];
                    $log['update_time']=$log['create_time']=time();
                    $this->Eth_coin_model->insert_db("coin_deposit_log",$log);
                    echo "\r\n Insert Transaction for coin ".$coin['name'].", address:".$to_address.",user id: ".$log['user_id']." at time:".date('Y-m-d H:i:s')."\r\n";
                }
            }
            echo "\r\n current block is :".$futureBlock." , the best block is :".$bestBlock." \r\n";
        }
        
        
        public function depositConfirm() {
            $latest_blocks = $this->getBestBlock();
            $deposits = $this->Eth_coin_model->get_eth_deposit_log($this->chain_id);
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $url=$this->config->item('ethApiUrl')."module=transaction&action=gettxreceiptstatus&txhash=".$tx_hash."&apikey=".$this->config->item("etherscanAPIkey");
                $result = commit_curl($url);
                if(empty(json_decode($result,true)['result'])){
                    $data['count'] = $item['count']+1;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易等待广播中...\r\n";
                    unset($data);
                    continue;
                }
                
                $status = intval(json_decode($result,true)['result']['status']);
                if ($status==1) {
                    $data['status'] = 200;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易确认\r\n";
                } elseif($status==0||$item['count']>100){
                    $data['status'] = 400;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_deposit_log($data,$item['id']);
                    echo $tx_hash." 交易失败\r\n";
                } 
                unset($data);
            }
        }
        
        public function get_balance($address) {
            $url=$this->config->item('ethApiUrl')."module=account&action=balance&address=".$address."&tag=latest&apikey=".$this->config->item("etherscanAPIkey");
            $result = commit_curl($url);
            $tmp=0;
            if(isset(json_decode($result,true)['result'])){
                $tmp = json_decode($result,true)['result'];
            }
            $balance = $tmp/1000000000000000000;
            echo "balance : ".$balance."\r\n";
            return $balance;
        }

        public function gen_tx($value,$from,$fromPri,$to) {
            
            $from = iconv(mb_detect_encoding($from, mb_detect_order(), true), "UTF-8", $from);
            $to = iconv(mb_detect_encoding($to, mb_detect_order(), true), "UTF-8", $to);
            $nonce = $this->get_nonce($from);
            $cnonce = '0x'.dechex($nonce);
            $param = [
                "nonce"     => $cnonce,
                "from"      => $from,
                "to"        => $to,
                "gas"       => "0x".dechex(21000),
                "gasPrice"  => "0x".dechex($this->config->item('gas_price')),
                "value"     => "0x".dechex($this->toWei($value)),
                'chainId'   =>  $this->config->item('eth_real_chain_id')
            ];
            var_dump($param);
            $transaction = new Transaction($param);
            $signedTransaction = $transaction->sign($fromPri);
            $method  = "eth_sendRawTransaction";
            $params  = ["0x".$signedTransaction];
            $out_arr = $this->call($method,$params);
            return $out_arr;
        }

        public function gen_tx_old($value,$from,$to) {
            
            $from = iconv(mb_detect_encoding($from, mb_detect_order(), true), "UTF-8", $from);
            $to = iconv(mb_detect_encoding($to, mb_detect_order(), true), "UTF-8", $to);
            $param = [
                "from"      => $from,
                "to"        => $to,
                "gas"       => "0x".dechex(21000),
                "gasPrice"  => "0x".dechex(20000000000),
                "value"     => "0x".dechex($this->toWei($value))
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
        public function collect() {
            // check network and local node
            $bestBlock = $this->getBestBlock();
            if($bestBlock==0){
                exit("Network problem\r\n");
            }
            $local_num = $this->ethSynced();
            if(!$local_num){
                exit("Local node is still syncing..\r\n");
            }
            echo "local number: ".$local_num."\r\n";
            echo "network number: ".$bestBlock."\r\n";

            $coin = $this->Eth_coin_model->get_eth_info();
            $ress = $this->Eth_coin_model->get_cold_wallet();
            foreach($ress as $res){
                    $from = $res['address'];
                    $fromPri = decrypt($res['private_key']);
                    $to = $this->config->item("ETHwithdrawTo");
                    $balance = $this->get_balance($from);
                    if($balance<$coin['min_amount']){
                        echo $from."该地址余额没有达到提现额度:".$coin['min_amount']." \r\n";
                        continue;
                    }
                    $value = $balance - 0.00042;
                    //$value = calculate($balance,strval(0.0005),"bcsub");
                    if($value<0){
                        echo $from."该地址余额小于手续费 \r\n";
                        continue;
                    }
                    $tx_res = $this->gen_tx($value,$from,$fromPri,$to);
                    echo "\r\n collect ETH sucessfully with tx hash : ".$tx_res."\r\n";
            }
        }
        public function collect_old() {
            // check network and local node
            $bestBlock = $this->getBestBlock();
            if($bestBlock==0){
                exit("Network problem\r\n");
            }
            $local_num = $this->ethSynced();
            if(!$local_num){
                exit("Local node is still syncing..\r\n");
            }
            echo "local number: ".$local_num."\r\n";
            echo "network number: ".$bestBlock."\r\n";

            $coin = $this->Eth_coin_model->get_eth_info();
            $ress = $this->Eth_coin_model->get_cold_wallet();
            foreach($ress as $res){
                    $from = $res['address'];
                    $fromPri = decrypt($res['private_key']);
                    $to = $this->config->item("ETHwithdrawTo");
                    $balance = $this->get_balance($from);
                    if($balance<$coin['min_amount']){
                        echo $from."该地址余额没有达到提现额度:".$coin['min_amount']." \r\n";
                        continue;
                    }
                    $value = $balance - 0.00042;
                    //$value = calculate($balance,strval(0.0005),"bcsub");
                    if($value<0){
                        echo $from."该地址余额小于手续费 \r\n";
                        continue;
                    }
                    if($from != $this->config->item('ethPayAccount')) {
                    //    $this->deleteDir($this->config->item('ETHkeystorePath'));
                        if(substr($from,0,2)=="0x"){
                            $filename=substr($from,2);
                        }
                        $is_exist = $this->isExist($this->config->item('ETHkeystorePath'),$filename);
                        if(!$is_exist){
                            echo "keystore file of ".$filename ."does not exists, need to import key\r\n";
                            $this->importKey($fromPri);
                        } else {
                            echo "keystore file of ".$filename ."already exists, no need to import key\r\n";
                        }
                    }
                    $tx_res = $this->gen_tx($value,$from,$to);
                    echo "\r\n collect ETH sucessfully with tx hash : ".$tx_res."\r\n";
            }
        }
        // Withdraw money to user
        public function withdraw() {
            // check network and local node
            $bestBlock = $this->getBestBlock();
            if($bestBlock==0){
                exit("Network problem\r\n");
            }
            $local_num = $this->ethSynced();
            if(!$local_num){
                exit("Local node is still syncing..\r\n");
            }
            echo "local number: ".$local_num."\r\n";
            echo "network number: ".$bestBlock."\r\n";

            $res = $this->Eth_coin_model->get_withdraw_log(210);
            $hot_wallet_private_key = decrypt($this->config->item('encrypted_hot_wallet'));
            foreach($res as $item) {
                $id = $item['id'];
                $hw_balance = $this->get_balance($this->config->item('ethPayAccount'));
            //    $total_cost = calculate(strval(0.0005),$item['coin_actual_amount'],"bcadd");
                $total_cost = $item['amount'] + 0.00042;
            //    $left = calculate($hw_balance,$total_cost,"bcsub");
                $left = $hw_balance - $total_cost;
                if($left < 0) {
                    echo "热钱包余额不足,热钱包余额: ".$hw_balance." ,实际需要花费: ".$total_cost." \r\n";
                    continue;
                }
                $tx_hash = $this->gen_tx($item['amount'],$this->config->item('ethPayAccount'),$hot_wallet_private_key,$item['to']);
                if(empty($tx_hash)||is_array($tx_hash) ) {
                     continue;
                } else {
                    $data['tx_hash'] = $tx_hash;
                    $data['status'] = 250;
                    $data['update_time'] = time();
                }
                echo date('Y-m-d H:i:s').":提现到:".$item['to']."成功,交易哈兮为:".$tx_hash."\r\n";
                $this->Eth_coin_model->update_withdraw_log($data,$id);
            }
        }

        public function withdrawConfirm() {
            $latest_blocks = $this->getBestBlock();
            $withdraws = $this->Eth_coin_model->get_withdraw_log(250);
            foreach($withdraws as $item) {
                $tx_hash = $item['tx_hash'];
                $url=$this->config->item('ethApiUrl')."module=transaction&action=gettxreceiptstatus&txhash=".$tx_hash."&apikey=".$this->config->item("etherscanAPIkey");
                $result = commit_curl($url);
                if(empty(json_decode($result,true)['result'])){
                    $data['count'] = $item['count']+1;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_withdraw_log($data,$item['id']);
                    echo $tx_hash." 交易等待广播中...\r\n";
                    unset($data);
                    continue;
                }
                
                $status = intval(json_decode($result,true)['result']['status']);
                if ($status==1) {
                    $data['status'] = 280;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_withdraw_log($data,$item['id']);
                    echo $tx_hash." 交易确认\r\n";
                } elseif($item['count']>100){
                    $data['status'] = 400;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_withdraw_log($data,$item['id']);
                    echo $tx_hash." 交易失败\r\n";
                } else if($status==0){
                    $data['count'] = $item['count']+1;
                    $data['status'] = 250;
                    $data['update_time'] = time();
                    $this->Eth_coin_model->update_withdraw_log($data,$item['id']);
                    echo $tx_hash." 本次交易失败，准备重试下次，重试次数:".$data['count']."...\r\n";
                }
                unset($data);
            }
        }
        
        // public function withdrawConfirm() {
        //     $res = $this->Eth_coin_model->get_withdraw_log(250);
        //     $complete = 0;
        //     foreach($res as $tx) {
        //         $id = $tx['id'];
        //         $method  = "eth_getTransactionByHash";
        //         $params  = [$tx['tx_hash']];
        //         $result = $this->call($method,$params);
        //         if( $result == null || $result['blockNumber'] == null) {
        //             var_dump($result);
        //             echo "emtpy tx : ".$tx['tx_hash']."\r\n";
        //             continue;
        //         } else if(isset($result['blockNumber']) && hexdec($result['blockNumber']) >0) {
        //             $data['status'] = 280;
        //             $data['update_time'] = time();
        //             $complete += 1;
        //             $this->Etc_coin_model->update_withdraw_log($data,$id);
        //         }
        //     }
        //     echo "Complete times:".$complete.",the time is :".date('Y-m-d H:i:s')."\r\n";
        // }
        
       
        
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
                     if(!strpos($file,$this->config->item('ETHreserveKeystore'))){
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

        private function isExist($dirPath,$filename) {
            $is_exist = false;
            if (! is_dir($dirPath)) {
                throw new InvalidArgumentException("$dirPath must be a directory");
            }
            if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
                $dirPath .= '/';
            }
            // delete all the tmp files
            $tmp_files = glob($dirPath . '*.tmp*');
            foreach ($tmp_files as $file) {
                if (is_dir($file)) {
                    continue;
                } else {
                    unlink($file);
                }
            }
            $files = glob($dirPath . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_dir($file)) {
                    continue;
                } else {
                     if(strpos($file,".UTC")){
                        unlink($file);
                        continue;
                     }
                     if(strpos($file,$filename)){
                         $is_exist = true;
                         break;
                     }
                }
            }
            return $is_exist;
        }

        private function ethSynced() {
            $method1  = "eth_syncing";
            $out_arr1 = $this->call($method1);
            echo "print eth syncing: \r\n";
            var_dump($out_arr1);
            $method2  = "eth_blockNumber";
            $out_arr2 = $this->call($method2);
            $num = hexdec($out_arr2);
            echo "print block number: \r\n";
            var_dump($num);
            if(!$out_arr1&&is_numeric($num)&&$num>0){
                return $num;
            } else {
                return false;
            }
        }
}


