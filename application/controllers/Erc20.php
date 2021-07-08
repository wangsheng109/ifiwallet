<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Erc20 extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Eth_coin_model');
            //    $this->rpc_url = "http://localhost:".$this->config->item('eth_port');
                $this->rpc_url = $this->config->item('remoteRPC');
                $this->chain_id = $this->config->item('erc20_chain_id');
                $this->tokens = $this->Eth_coin_model->get_erc20_tokens($this->chain_id);
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
        public function deposit() {
            $bestBlock = $this->getBestBlock();
            if($bestBlock==0){
                exit("Network problem\r\n");
            }
            $ress = $this->Eth_coin_model->get_cold_wallet();
            $all_address = array();
            $address_user = array();
            foreach($ress as $res){
                $all_address[]=strtolower($res['address']);
                $address_user[strtolower($res['address'])]=$res['user_id'];
            }
            $futureBlock = 0;
            foreach($this->tokens as $token){
                $decimal = intval($token['decimals']);
                $dec = 1;
                for($i=0;$i<$decimal;$i++){
                    $dec*=10;
                }
                $start_block=$token["next_start"];
                $end_block=intval($start_block)+100;
                $contract= $token["contract"];
                $url=$this->config->item('ethApiUrl')."module=account&action=txlist&address=".$contract."&startblock=".$start_block."&endblock=".$end_block."&sort=asc&apikey=".$this->config->item("etherscanAPIkey");
                $raw_txs_output = commit_curl($url);
                
                $futureBlock=($end_block+1<$bestBlock)?$end_block+1:$bestBlock;
                $this->Eth_coin_model->update_coins(['next_start'=>$futureBlock],$token['id']);
                if(empty(json_decode($raw_txs_output,true)['result'])){
                    continue;
                }
                
                $raw_txs = json_decode($raw_txs_output,true)['result'];
                if(!is_array($raw_txs)||count($raw_txs)<1){
                    var_dump($raw_txs);
                    continue;
                }
                foreach($raw_txs as $raw_tx){
                    $input = $raw_tx['input'];
                    $input1 = substr($input,0,-64);
                    $to_address = "0x".substr($input1,-40);
                    if(!in_array(strtolower($to_address),$all_address)){
                        continue;
                    }
                    $input2=substr($input,-64);
                    $value=hexdec($input2);
                    $log=array();
                    $log['user_id']=$address_user[strtolower($to_address)];
                    $log['chain_id']=$this->chain_id;
                    $log['coin_id']=$token['id'];
                    $log['address']=$to_address;
                    $log['tx_hash']=$raw_tx['hash'];
                    $log['amount']=$value/$dec;
                    $log['status']=100;
                    $log['tx_timestamp']=$raw_tx['timeStamp'];
                    $log['update_time']=$log['create_time']=time();
                    $this->Eth_coin_model->insert_db("coin_deposit_log",$log);
                    echo "\r\n Insert Transaction for coin ".$token['name'].", tx:".$log['tx_hash'].",user id: ".$log['user_id'].", amount:".$log['amount']." at time:".date('Y-m-d H:i:s')."\r\n";
                }
            }
            echo "\r\n current block is :".$futureBlock." , the best block is :".$bestBlock." \r\n";
        }

        public function depositConfirm() {
            $latest_blocks = $this->getBestBlock();
            $deposits = $this->Eth_coin_model->get_erc20_deposit_log($this->chain_id);
            foreach($deposits as $item) {
                $tx_hash = $item['tx_hash'];
                $url=$this->config->item('ethApiUrl')."module=transaction&action=gettxreceiptstatus&txhash=".$tx_hash."&apikey=".$this->config->item("etherscanAPIkey");
                $result = commit_curl($url);
                if(!isset(json_decode($result,true)['result'])){
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

        public function collect()
        {
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
            $tokens = array();
            foreach($this->tokens as $k => $token){
                $tokens[$k]['token_name'] = $token['name'];
                $tokens[$k]['contract'] = $token['contract'];
                $tokens[$k]['min_amount'] = $token['min_amount'];
                $decimal = intval($token['decimals']);
                $dec = 1;
                for($i=0;$i<$decimal;$i++){
                    $dec*=10;
                }
                $tokens[$k]['dec'] = $dec;
                $tokens[$k]['decimals'] = $token['decimals'];
            }

            $ress = $this->Eth_coin_model->get_cold_wallet();
        //    $this->deleteDir($this->config->item('ETHkeystorePath'));
            foreach($ress as $res){
                    $from = $res['address'];
                    $fromPri = decrypt($res['private_key']);
                    $to = $this->config->item("ETHwithdrawTo");
                    $result=$this->send_tokens($from,$fromPri,$to,$tokens);
            }
        }
        public function withdraw()
        {
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
            $tokens = array();
            foreach($this->tokens as $k => $token){
                $tokens[$k]['token_name'] = $token['name'];
                $tokens[$k]['contract'] = $token['contract'];
                $tokens[$k]['min_amount'] = $token['min_amount'];
                $decimal = intval($token['decimals']);
                $tokens[$k]['decimals'] = $decimal;
                $dec = 1;
                for($i=0;$i<$decimal;$i++){
                    $dec*=10;
                }
                $tokens[$k]['dec'] = $dec;
            }
            $withdraws = $this->Eth_coin_model->get_erc20_withdraw_log($this->chain_id);
            foreach($withdraws as $withdraw){
                $coin_id = $withdraw['coin_id'];
                $token = $tokens[$coin_id];
                if(empty($token['token_name'])){
                    echo "\r\n coin id ".$coin_id ."not exists\r\n";
                    continue;
                }
                $amount = $this->toWei($withdraw['actual_amount'],$token['decimals']);
                // if(!$this->unlockAccount($this->config->item('ethPayAccount'))){
                //     echo "\r\n unlock fail\r\n";
                //     return null;
                // }
                if(strtolower($token['token_name'])=="usdt"){
                    $tx_res = $this->send_token($amount,$this->config->item('ethPayAccount'),'',$token['contract'],$withdraw['to'],1);
                } else {
                    $tx_res = $this->send_token($amount,$this->config->item('ethPayAccount'),'',$token['contract'],$withdraw['to']);
                }
                if(!is_array($tx_res)){
                    $update_arr = array(
                        "tx_hash"   =>  $tx_res,
                        "status"    =>  250,
                        "update_time"   =>  time()
                    );
                    $this->Eth_coin_model->update_db("coin_withdraw_log",$update_arr,array('id'=>$withdraw['id']));
                    echo "\r\n send token ".$token['token_name']." sucessfully with tx hash : ".$tx_res."\r\n";
                } else {
                    var_dump($tx_res);
                }
            }

        }
        public function withdrawConfirm() {
            $latest_blocks = $this->getBestBlock();
            $withdraws = $this->Eth_coin_model->get_erc20_withdraw_log($this->chain_id,null,1);
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
        public function get_token_balance($address,$contract,$dec){
            if(substr($address,0,2)=="0x"){
                $address=substr($address,2);
            } 
            $funcSelector = "0x70a08231";
            $data = $funcSelector . "000000000000000000000000" . $address;
            $method  = "eth_call";
            $param1 = [
                "data"  => $data,
                "to"    =>  $contract
            ];
            $params  = [$param1,"latest"];
            $result = $this->call($method,$params);
            return (hexdec($result)/$dec);
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
        
        public function send_tokens($from,$fromRaw,$to,$tokens){
            
            // if($fromRaw != $this->config->item('ethPayAccount')) {
            //     if(substr($from,0,2)=="0x"){
            //         $filename=substr($from,2);
            //     }
            //     $is_exist = $this->isExist($this->config->item('ETHkeystorePath'),$filename);
            //     if(!$is_exist){
            //         echo "keystore file of ".$filename ."does not exists, need to import key\r\n";
            //         $from_result = $this->importKey($fromRaw);
            //     } else {
            //         echo "keystore file of ".$filename ."already exists, no need to import key\r\n";
            //     }
            // }
            
            $from = iconv(mb_detect_encoding($from, mb_detect_order(), true), "UTF-8", $from);
            // if(!$this->unlockAccount($from)){
            //     echo "unlock fail\r\n";
            //     return null;
            // }
            foreach($tokens as $token){
                $balance = $this->get_token_balance($from,$token['contract'],$token['dec']);
		        echo "\r\n from:".$from."\r\n";
		        echo "\r\n contract:".$token['contract']."\r\n";
		        echo "\r\n dec:".$token['dec']."\r\n";
		        echo "\r\n balance:".$balance."\r\n";
                if($balance==0){
		            echo "\r\n token balance is 0 \r\n";
                    continue;
                }
                if($balance<$token['min_amount']){
                    echo "\r\n The balance of ".$from." : ".$balance." for token name ".$token['token_name']." is less than the min_amount: ".$token['min_amount']."\r\n";
                    continue;
                }
                $amount = $this->toWei($balance,$token['decimals']);
		        if($token['token_name']=='usdt'){
		            echo "for usdt:\r\n";
                    $tx_res = $this->send_token($amount,$from,$fromRaw,$token['contract'],$to,1);
                //    $tx_res = $this->send_token_old($amount,$from,$token['contract'],$to,1);
		        }else {
                    $tx_res = $this->send_token($amount,$from,$fromRaw,$token['contract'],$to);
                //    $tx_res = $this->send_token_old($amount,$from,$token['contract'],$to);
		        }
                if(!is_array($tx_res)){
                    echo "\r\n collect token ".$token['token_name']." sucessfully with tx hash : ".$tx_res."\r\n";
                } else {
                    var_dump($tx_res);
		            echo "\r\n this is something wrong when send token \r\n";
                }
            }
        }
        private function send_token($amount,$from,$privateKey,$contract,$to,$type=0)
        {
            $hot_wallet_private_key = decrypt($this->config->item('encrypted_hot_wallet'));
	        $eth_balance = $this->get_eth_balance($from);
            if($from!==$this->config->item('ethPayAccount')&&$eth_balance<0.001){
                echo "\r\n eth balance is too low, which is :".$eth_balance."\r\n";
                echo "\r\n will send 0.01ETH to address :".$from."\r\n";
                // send 0.01 ETH gas to this address
                $hot_wallet = $this->config->item('ethPayAccount');
                $eth_hex = dechex(10000000000000000);
                $nonce = $this->get_nonce($hot_wallet);
                $cnonce = '0x'.dechex($nonce);
                $param = [
                    "nonce"     => $cnonce,
                    "from"      => $hot_wallet,
                    "to"        => $from,
                    "gas"       => "0x".dechex(21000),
                    "gasPrice"  => "0x".dechex($this->config->item('gas_price')),
                    "value"     =>  "0x".$eth_hex,
                    'chainId'   =>  $this->config->item('eth_real_chain_id')
                ];
                $transaction = new Transaction($param);
                $signedTransaction = $transaction->sign($hot_wallet_private_key);
                $method  = "eth_sendRawTransaction";
                $params  = ["0x".$signedTransaction];
                $out_arr = $this->call($method,$params);
                return false;
            } else if($from==$this->config->item('ethPayAccount')){//提现
                if(substr($to,0,2)=="0x"){
                    $to=substr($to,2);
                }
                $funcSelector = "0xa9059cbb";
                $amt_hex = dechex($amount);
                $data = $funcSelector . "000000000000000000000000" . $to;
                
                if(substr($amt_hex,0,2)=="0x"){
                    $amt_hex=substr($amt_hex,2);
                } 
                $len=strlen($amt_hex);
                $amt_val = "";
                $i=0;
                while($i<64-$len){
                    $amt_val.="0";
                    $i++;
                }
                $amt_val.=$amt_hex;
                $data .=$amt_val;
                $gas = '0x'.dechex(53334);
                $gasPrice = '0x'.dechex($this->config->item('gas_price'));
                if($type==1){
                    $gas = '0x'.dechex(93334);
                }
                $nonce = $this->get_nonce($from);
                $cnonce = '0x'.dechex($nonce);
                $param = [
                    "nonce"     => $cnonce,
                    "from"      => $from,
                    "to"        => $contract,
                    "gas"       => $gas,
                    "gasPrice"  => $gasPrice,
                    "data"     => $data,
                   'chainId'   =>  $this->config->item('eth_real_chain_id')
                ];
                $transaction = new Transaction($param);
                $signedTransaction = $transaction->sign($hot_wallet_private_key);
                $method  = "eth_sendRawTransaction";
                $params  = ["0x".$signedTransaction];
                $out_arr = $this->call($method,$params);
                return $out_arr;
            } else {//归集
                if(substr($to,0,2)=="0x"){
                    $to=substr($to,2);
                }
                $funcSelector = "0xa9059cbb";
                $amt_hex = dechex($amount);
                $data = $funcSelector . "000000000000000000000000" . $to;
                
                if(substr($amt_hex,0,2)=="0x"){
                    $amt_hex=substr($amt_hex,2);
                } 
                $len=strlen($amt_hex);
                $amt_val = "";
                $i=0;
                while($i<64-$len){
                    $amt_val.="0";
                    $i++;
                }
                $amt_val.=$amt_hex;
                $data .=$amt_val;
                $gas = '0x'.dechex(53334);
                $gasPrice = '0x'.dechex($this->config->item('gas_price'));
                if($type==1){
                    $gas = '0x'.dechex(93334);
                }
                $nonce = $this->get_nonce($from);
                $cnonce = '0x'.dechex($nonce);
                $param = [
                    'nonce'     => $cnonce,
                    'from'      => $from,
                    'to'        => $contract,
                    'gas'       => $gas,
                    'gasPrice'  => $gasPrice,
                    'data'      => $data,
                    'chainId'   =>  $this->config->item('eth_real_chain_id')
                ];
                $transaction = new Transaction($param);
                $signedTransaction = $transaction->sign($privateKey);
                $method  = "eth_sendRawTransaction";
                $params  = ["0x".$signedTransaction];
                $out_arr = $this->call($method,$params);
                return $out_arr;
            }
        }

        private function send_token_old($amount,$from,$contract,$to,$type=0)
        {
	        
            $eth_balance = $this->get_eth_balance($from);
            if($from!==$this->config->item('ethPayAccount')&&$eth_balance<0.001){
                echo "\r\n eth balance is too low, which is :".$eth_balance."\r\n";
                echo "\r\n will send 0.01ETH to address :".$from."\r\n";
                // send 0.01 ETH gas to this address
                $hot_wallet = $this->config->item('ethPayAccount');
                $this->unlockAccount($hot_wallet);
                $eth_hex = dechex(10000000000000000);
                $param = [
                    "from"      => $hot_wallet,
                    "to"        => $from,
                    "gas"       => "0x".dechex(21000),
                    "gasPrice"  => "0x".dechex(20000000000),
                    "value"     =>  "0x".$eth_hex
                ];
                $method  = "eth_sendTransaction";
                $params  = [$param];
                $new_out=$this->call($method,$params);   
                var_dump($new_out);
                return false;
            } else {
                if(substr($to,0,2)=="0x"){
                    $to=substr($to,2);
                }
                $funcSelector = "0xa9059cbb";
                $amt_hex = dechex($amount);
                $data = $funcSelector . "000000000000000000000000" . $to;
                
                if(substr($amt_hex,0,2)=="0x"){
                    $amt_hex=substr($amt_hex,2);
                } 
                $len=strlen($amt_hex);
                $amt_val = "";
                $i=0;
                while($i<64-$len){
                    $amt_val.="0";
                    $i++;
                }
                $amt_val.=$amt_hex;
                $data .=$amt_val;
                $gas = '0x'.dechex(53334);
                $gasPrice = '0x'.dechex(10000000000);
                if($type==1){
                    $gas = '0x'.dechex(93334);
                }
                $param = [
                    "from"      => $from,
                    "to"        => $contract,
                    "gas"       => $gas,
                    "gasPrice"  => $gasPrice,
                    "data"     => $data
                ];
                $method  = "eth_sendTransaction";
                $params  = [$param];
                $out_arr = $this->call($method,$params);
                return $out_arr;
            }
        }
        
        public function get_eth_balance($address) {
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
        
        private function toWei($value,$dec) {
            $float = 0;
            switch(intval($dec)){
                case 1:
                    $float = $value*(1.0E+1);
                    break;
                case 2:
                    $float = $value*(1.0E+2);
                    break;
                case 3:
                    $float = $value*(1.0E+3);
                    break;
                case 4:
                    $float = $value*(1.0E+4);
                    break;  
                case 5:
                    $float = $value*(1.0E+5);
                    break;
                case 6:
                    $float = $value*(1.0E+6);
                    break;
                case 7:
                    $float = $value*(1.0E+7);
                    break;
                case 8:
                    $float = $value*(1.0E+8);
                    break;
                case 9:
                    $float = $value*(1.0E+9);
                    break;
                case 10:
                    $float = $value*(1.0E+10);
                    break;
                case 11:
                    $float = $value*(1.0E+11);
                    break;
                case 12:
                    $float = $value*(1.0E+12);
                    break;  
                case 13:
                    $float = $value*(1.0E+13);
                    break;
                case 14:
                    $float = $value*(1.0E+14);
                    break;
                case 15:
                    $float = $value*(1.0E+15);
                    break;
                case 16:
                    $float = $value*(1.0E+16);
                    break; 
                case 17:
                    $float = $value*(1.0E+17);
                    break;
                case 18:
                    $float = $value*(1.0E+18);
                    break;
                default:
                    $float = $value*(1.0E+1);
                    break;
            }
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


