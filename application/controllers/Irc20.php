<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Irc20 extends MY_Controller {
    
        public function __construct()
        {
            parent::__construct();
            $this->load->model('ifi_coin_model');
            $this->rpc_url = $this->config->item('ifiRPC');
            $this->coin_name = "ifi";
            $this->coin_id = $this->config->item('ifi_coin_id');
            $this->chain_id = $this->config->item('ifi_chain_id');
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
        
        public function send_ifi()
        {
            $to = $_GET['address'];
            $amount = isset($_GET['amount'])?$_GET['amount'] : 9;
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $contract = "0x4D2f63d6826603B84D12C1C7dd33aB7F3BDe7553";
            $tx_res = $this->send_token($amount,$from,$fromPri,$contract,$to);
            echo "\r\n send ifi sucessfully with tx hash : ".$tx_res."\r\n";
        }

     

    private function send_token($amount, $from, $privateKey, $contract, $to, $type = 0)
    {

        if (substr($to, 0, 2) == "0x") {
            $to = substr($to, 2);
        }
        $funcSelector = "0xa9059cbb";
        // $amt_hex = base_convert($amount,10,16);
        $amt_hex = $amount;
        $data = $funcSelector . "000000000000000000000000" . $to;

        if (substr($amt_hex, 0, 2) == "0x") {
            $amt_hex = substr($amt_hex, 2);
        }
        $len = strlen($amt_hex);
        $amt_val = "";
        $i = 0;
        while ($i < 64 - $len) {
            $amt_val .= "0";
            $i++;
        }
        $amt_val .= $amt_hex;
        $data .= $amt_val;
        $gas = '0x' . dechex(93334);
        $gasPrice = '0x' . dechex($this->config->item('gas_price'));
        if ($type == 1) {
            $gas = '0x' . dechex(93334);
        }
        $nonce = $this->get_nonce($from);
        $cnonce = '0x' . dechex($nonce);
        $param = [
            'nonce'     => $cnonce,
            'from'      => $from,
            'to'        => $contract,
            'gas'       => $gas,
            'gasPrice'  => $gasPrice,
            'data'      => $data,
            'chainId'   =>  $this->config->item('ifi_real_chain_id')
        ];
        $transaction = new Transaction($param);
        $signedTransaction = $transaction->sign($privateKey);
        $method  = "eth_sendRawTransaction";
        $params  = ["0x" . $signedTransaction];
        $out_arr = $this->call($method, $params);
        return $out_arr;
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


