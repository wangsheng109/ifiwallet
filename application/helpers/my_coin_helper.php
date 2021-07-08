<?php
/***
 * commit api for get and post 
 * 
 */
function commit_curl($url,$get=true,$header=0,$odata=null,$user=null,$pass=null) {
            
            $ch = curl_init(); 
            curl_setopt($ch, CURLOPT_URL, $url); 
            if(!$get){
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            if ($header == 3) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); 
            if($user!=null && $pass!=null) {
                curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
            }
            if($header==0) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
            } else if($header==1 || $header==3 || $header==4) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8','Accept: application/json; charset=utf-8'));
            } else if ($header==2) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json; charset=utf-8','Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
            } else if($header==5) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain;','Accept: text/plain;'));
            }
            if($odata != null && $header==1 && !$get) {
                curl_setopt($ch, CURLOPT_POSTFIELDS , json_encode($odata));
            } else if($odata != null && $header==2 && !$get) {
                curl_setopt($ch, CURLOPT_POSTFIELDS , http_build_query($odata));
            } else if($odata != null && $header==4 && !$get) {
                curl_setopt($ch, CURLOPT_POSTFIELDS , $odata);
            } else if($odata != null && $header==5 && !$get) {
                curl_setopt($ch, CURLOPT_POSTFIELDS , json_encode($odata));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            $output = curl_exec($ch); 
            curl_close($ch);
            return $output;
        }
        
        /**
         * encrypt and decrypt functions
         * 
         */
        function encrypt($data) {
            $key = config_item('cryptoKey');
            $iv =  config_item('cryptoIV');
            $key = iconv(mb_detect_encoding($key, mb_detect_order(), true), "UTF-8", $key);
            $iv = iconv(mb_detect_encoding($iv, mb_detect_order(), true), "UTF-8", $iv);
            $data = iconv(mb_detect_encoding($data, mb_detect_order(), true), "UTF-8", $data);
            return openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        }
        
        function decrypt($data) {
            $key = config_item('cryptoKey');
            $iv = config_item('cryptoIV');
            $key = iconv(mb_detect_encoding($key, mb_detect_order(), true), "UTF-8", $key);
            $iv = iconv(mb_detect_encoding($iv, mb_detect_order(), true), "UTF-8", $iv);
            $data = iconv(mb_detect_encoding($data, mb_detect_order(), true), "UTF-8", $data);
            return openssl_decrypt($data, 'aes-256-cbc', $key, 0, $iv);
        }
        
        function sign_content($content) {
            $sault = config_item('sault');
            $sign = md5(md5($content).$sault);
            return $sign;
        }
        
        /**
	 * Calculate Fee
	 * @param integer $input_length
	 * @param integer $output_length
	 */
	function calculateFee($input_length, $output_length) {
		$total_byte = ($input_length * 180) + (34 * $output_length) + 10 + 40;
                $max_float = getFloatLength($total_byte) > getFloatLength(config_item('FEE_PER_BYTE'))?getFloatLength($total_byte):getFloatLength(config_item('FEE_PER_BYTE'));
		$fee = bcmul($total_byte, config_item('FEE_PER_BYTE'), $max_float) + 0;
		return $fee;
	}

	/**
	 * 获取小数位数
	 *
	 * @param [type] $number
	 * @return void
	 */
	function getFloatLength($number) {
		$number = strval($number);
		$count = 0;
		$temp = explode('.', $number);
		if (sizeof($temp) > 1) {
			$decimal = end($temp);
			$count = strlen($decimal);
		}
		
		return $count;
	}
        
        /**
	 * 加减乘除(BCMATH)
	 *
	 * @param decimal $left_operand
	 * @param decimal $right_operand
	 * @param string $bcmethod
	 * @return decimal $result
	 */
	function calculate($left_operand, $right_operand, $bcmethod) {
		$left_operand_decimal = getFloatLength($left_operand);
		$right_operand_decimal = getFloatLength($right_operand);
		$left_operand = number_format($left_operand, $left_operand_decimal, '.', ''); // 保证该数不是科学计数法格式
		$right_operand = number_format($right_operand, $right_operand_decimal, '.', '');
		$result = $bcmethod($left_operand, $right_operand, $left_operand_decimal >= $right_operand_decimal ? $left_operand_decimal : $right_operand_decimal);
		return $result + 0;
	}
        
        /**
	 * 数字格式化
	 *
	 * @param integer|string $number
	 * @return stringnumber $number
	 */
	function numberFormat($number) {
		$number_decimal = getFloatLength($number);
		$number = number_format($number, $number_decimal, '.', '');
		return $number;
	}

