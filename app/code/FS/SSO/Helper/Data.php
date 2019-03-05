<?php

namespace FS\SSO\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	public function __construct( Context $context,
        ScopeConfigInterface $ScopeConfig,
		CountryCollectionFactory $countryCollectionFactory
		){
		$this->_scopeConfig = $ScopeConfig;
		$this->_countryCollectionFactory = $countryCollectionFactory;
        parent::__construct($context);
	}
	public function APICr(){
		$api['cleint_id'] = $this->_scopeConfig->getValue("sso/general/api_client");
		$api['cleint_secret'] = $this->_scopeConfig->getValue("sso/general/api_secret");
		$api['URL'] = $this->_scopeConfig->getValue("sso/general/api_url");
		$api['phrase'] = $this->_scopeConfig->getValue("sso/general/api_phrase"); 
		return $api;
	}
	
	public function getToken($Auth,$redirect,$regenrate=null){
		if($regenrate){
			$parms = json_encode(array("grant_type"=>"refresh_token","redirect_uri"=>$redirect,"refresh_token"=>$regenrate));
		}else{
			$parms = json_encode(array("grant_type"=>"authorization_code","code"=>$Auth,"redirect_uri"=>$redirect));
		}
		
		$api = $this->APICr();
		$authr = base64_encode($api['cleint_id'].":".$api['cleint_secret']); 
			
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$api['URL']."oauth/token");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Basic '.$authr
		));
		// In real life you should use something like:
		// curl_setopt($ch, CURLOPT_POSTFIELDS, 
		//          http_build_query(array('postvar1' => 'value1')));

		// Receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);

		curl_close ($ch);
		return $server_output;
	}
	public function verifyToken($token){
		$api = $this->APICr();
		$api['URL']."oauth/verify-access?access_token=".$token; 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$api['URL']."oauth/verify-access?access_token=".$token);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$server_output = curl_exec($ch);

		curl_close ($ch);
		return $server_output;
	}
	
	public function getAvailableCountries()
    {
        $collection = $this->_countryCollectionFactory->create();
        //$collection->addFieldToSelect('*');
		foreach($collection->toOptionArray() as $countries){
			$country[$countries['value']] = $countries['label'];
		}
        return $country;
    }
	
	public function getStates($countryId,$stateArr){
		foreach($stateArr as $Id => $state){
			$region[$Id] = $state['name'];
		}
		//echo "<pre>"; print_r($region); die;
		return $region;
	}
	
	public function generateOTP($Auth,$mobile){
		//echo $Auth;die;
		$apik = $this->APICr(); 
		$parms = json_encode(array("phone_no"=>$mobile));
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$apik['URL']."user-management/user/otp/generate");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Bearer '.$Auth
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);

		curl_close ($ch);
		return $server_output;
	}
	
	public function verifyOTP($Auth,$mobile,$otp){
		//
		$apik = $this->APICr();
		$parms = json_encode(array("phone_no"=>$mobile,"otp"=>$otp));
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$apik['URL']."user-management/user/otp/verify");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Bearer '.$Auth
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);

		curl_close ($ch);
		return $server_output;
	}
	public function updateUser($Auth,$postData){
		$apik = $this->APICr();
		$parms = json_encode($postData);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$apik['URL']."user-management/user/profile");
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Bearer '.$Auth
		));

		$server_output = curl_exec($ch);
		
		curl_close ($ch);
		
		$output = json_decode($server_output, true);
		if(array_key_exists('error',$output)){
			return "error";
		}else{
			return "success";
		}
	}
	
}

