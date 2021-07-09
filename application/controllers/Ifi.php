<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Ifi extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('ifi_coin_model');
                $this->rpc_url = $this->config->item('ifiRPC');
                $this->coin_name = "ifi";
                $this->coin_id = $this->config->item('ifi_coin_id');
                $this->chain_id = $this->config->item('ifi_chain_id');
        }
        
        public function decrypt_tool() {
            echo decrypt($this->config->item('test_private_key'))."\r\n";
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

        
        public function createAccount() {
            $accounts_count = $this->Ifi_coin_model->get_coin_accounts_count();
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
            echo "成功创建了 ".$n." 个IFI新账号:".date('Y-m-d H:i:s')."\r\n";
        }

        public function testAccount() {
            $privateKey = bin2hex(openssl_random_pseudo_bytes(32));
            $transaction = new Transaction(NULL);
            $address = $transaction->privateKeyToAddress($privateKey);
            echo "\r\n private key : ".$privateKey." \r\n";
            echo "\r\n address : ".$address."\r\n";
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
                "value"     => "0x".base_convert($this->toWei($value),10,16),
                'chainId'   =>  $this->config->item('ifi_real_chain_id')
            ];
            // var_dump($param);
            $transaction = new Transaction($param);
            $signedTransaction = $transaction->sign($fromPri);
            $method  = "eth_sendRawTransaction";
            $params  = ["0x".$signedTransaction];
            $out_arr = $this->call($method,$params);
            return $out_arr;
        }

        private function get_balance($address, $model="gastracker") {
            $method="eth_getBalance";
            $params=[$address,"latest"];
            $result = hexdec($this->call($method,$params));
            return $result;
        }


        
        public function send_ifie() {
            $to = $_GET['address'];
            $amount = isset($_GET['amount'])?$_GET['amount'] : 10;
            $amount = is_numeric($amount)?$amount : 10;
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $tx_res = $this->gen_tx(floatval($amount),$from,$fromPri,$to);
            echo "\r\n send ifie sucessfully with tx hash : ".$tx_res."\r\n";
        }
        
        private function toWei($value) {
            $float = floatval($value*(1.0E+18));
            return number_format($float,0,'.','');
        }
        
        private function toEther($value) {
            $float = $value/(1.0E+18);
            return number_format($float,4,'.','');
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


