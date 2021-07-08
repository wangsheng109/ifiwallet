<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Test extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Eth_coin_model');
                $this->rpc_url = "http://localhost:".$this->config->item('eth_port');
                
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

        public function send_raw_tx($str) {
            $url=$this->config->item('ethApiUrl')."module=proxy&action=eth_sendRawTransaction&hex=".$str."&tag=latest&apikey=".$this->config->item("etherscanAPIkey");
            $result = commit_curl($url);
            $tmp=0;
            if(isset(json_decode($result,true)['result'])){
                $tx_hash = json_decode($result,true)['result'];
                return $tx_hash;
            }
            var_dump($result);
            return null;
        }

        public function eth_local_balance($address) {
            $method  = "eth_getBalance";
            $params  = [$address,"latest"];
            return $this->call($method,$params);
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

        public function sign()
        {
            $transaction = new Transaction([
                'nonce' => '0x01',
                'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
                'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
                'gas' => '0x76c0',
                'gasPrice' => '0x9184e72a000',
                'value' => '0x9184e72a',
                'data' => '0xd46e8dd67c5d32be8d46e8dd67c5d32be8058bb8eb970870f072445675058bb8eb970870f072445675'
            ]);
            $signedTransaction = $transaction->sign('C87509A1C067BBDE78BEB793E6FA76530B6382A4C0241E5E4A9EC0A0F44DC0D3');
            var_dump($signedTransaction);
        }

        public function send_token()
        {
            $original_amount =   floatval($this->config->item("test_amount"));
            $dec    =   intval($this->config->item("test_dec"));
            $from   =   $this->config->item("test_from");
            $privateKey = $this->config->item("test_privateKey");
            $contract = $this->config->item("test_contract");
            $to         =   $this->config->item("test_to");
            $type       =   $this->config->item("test_type");
            echo "\r\n get local balance: \r\n";
            $local_balance = $this->eth_local_balance($from);
            var_dump($local_balance);
            // get eth balance
            $eth_balance = $this->get_eth_balance($from);
            echo "\r\n eth balance of ".$from." is ".$eth_balance." \r\n";
            $token_balance = $this->get_token_balance($from,$contract,$this->toWei(1,$dec));
            echo "\r\n token balance of ".$from." is ".$token_balance." \r\n";
            $amount = $this->toWei($original_amount,$dec);
            echo "\r\n original amount is ".$original_amount."\r\n";
            echo "\r\n to wei amount is ".$amount."\r\n";
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
            $nonce = $this->get_nonce($from);
            $cnonce = '0x'.dechex($nonce);
            $param = [
                'nonce' => $cnonce,
                "from"      => $from,
                "to"        => $contract,
                "gas"       => $gas,
                "gasPrice"  => $gasPrice,
                "data"     => $data,
                "chainId"   =>  3
            ];
            var_dump($param);
            $transaction = new Transaction($param);
            $signedTransaction = $transaction->sign($privateKey);
            $method  = "eth_sendRawTransaction";
            $params  = ["0x".$signedTransaction];
            var_dump($params);
            $out_arr = $this->call($method,$params);
        //    $out_arr = $this->send_raw_tx("0x".$signedTransaction);
            var_dump($out_arr);
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
        
}


