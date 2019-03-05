<?php

namespace FS\SSO\Cron;

use Magento\Framework\App\Action\Context;
use \Magento\Store\Model\StoreManagerInterface;

class CustomerSync 
{
	protected $_scopeConfig;
	protected $_helper;
	protected $addressDataFactory;
	protected $addressRepository;
	protected $_storeManager;
	protected $counter;
   public function __construct(
   Context $context,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory,	
		\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerFactory,
		\FS\SSO\Helper\Data  $helper,
		StoreManagerInterface $storeManager,
		\Magento\Customer\Model\CustomerFactory $customerFactory2,
		\Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
		\Magento\Customer\Api\Data\RegionInterfaceFactory $regionFactory,
		\Magento\Directory\Helper\Data	$regionHelper
		) {
		$this->regionFactory = $regionFactory;
		$this->_customerFactory = $customerFactory;
        $this->_urlInterface = $urlInterface;
		$this->helper = $helper;
        $this->addressDataFactory = $addressDataFactory;
		$this->storeManager     = $storeManager;
		$this->customerFactory  = $customerFactory2;
		$this->addressRepository = $addressRepository;
		$this->regionHelper = $regionHelper;
    }
	
	public function execute()
	{
		$limit = 50;
		$lastDate = $this->_customerFactory->create()
		->setOrder('modify_date', 'desc')->getFirstItem()->getModifyDate();
		$usersResp = $this->getUser($lastDate,'0',$limit);
		$this->counter= $limit;
		$users = json_decode($usersResp, true);
		$this->processCustomer($users, $this->counter, $limit);
	}
	
	protected function getUser($after, $offset,$limit){
		$parms = json_encode(array("after"=>$after,"offset"=>$offset,"limit"=>$limit));
		
		$api = $this->helper->APICr();
		echo $api['URL']."user-management/users"; die;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$api['URL']."user-management/users");
		//curl_setopt($ch, CURLOPT_POST, 1);
		//curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json',//'Authorization:Bearer '.$Auth
		));
		// In real life you should use something like:
		// curl_setopt($ch, CURLOPT_POSTFIELDS, 
		//          http_build_query(array('postvar1' => 'value1')));

		// Receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec($ch);

		curl_close ($ch);
		//echo "User profile=<pre>"; print_r(json_decode($server_output,true)); 
		//die;
		return $server_output;
		// Further processing ...
		//if ($server_output == "OK") { ... } else { ... }
	}

	protected function GenrateCustomer($userInfo){
		//echo "<pre>"; print_r($userInfo); die; 
		$gender=["male"=>"1","female"=>"2"];
		$country = $this->helper->getAvailableCountries();
		$customerCollection = $this->_objectManager->create('\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory')->create();
	 	// Check for Mobile already exist 
		
		$customereCollection->addAttributeToFilter(
			array(
				array('attribute'=> 'email','eq' => $userInfo['email']),
				array('attribute'=> 'mobile','eq' => $userInfo['phone']),
				/* array('attribute'=> 'uuid','eq' => $userInfo['uuid']),	
				array('attribute'=> 'regenrate_token','eq' => $token_resp['refresh_token']) */
			),null,'left'
		);
			
			if($customerCollection->getSize()>0){
				$customer = $customerCollection->getFirstItem();// die;
				$customer->setMobile($userInfo['phone'])
				->setEmail($userInfo['email'])
				->setFirstname($name[0])
				->setLastname((isset($name[1])?$name[1]:$name[0]))
				->setGender($gender[strtolower($userInfo['gender'])])
				->setPassword("123456");
				
			}else{
				//echo "mobile not found"; die;
			
				$websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
				// Instantiate object (this is the most important part)
				$customer   = $this->customerFactory->create();
				$customer->setWebsiteId($websiteId);
				$name = explode(" ",$userInfo['name']);
				// Preparing data for new customer
				$customer->setEmail($userInfo['email']); 
				$customer->setFirstname($name[0]);
				$customer->setLastname((isset($name[1])?$name[1]:$name[0]));
				$customer->setMobile($userInfo['phone']);
				$customer->setGender($gender[strtolower($userInfo['gender'])]);
				$customer->setPassword("123456");
				 $customerData  = $customer->getDataModel();
				$customerData->setCustomAttribute('modify_date',$userInfo['lastModifiedDate']);
				$customer->updateData($customerData); 
				
			}
				$customer->save();
				$countryId = array_search($userInfo['country'],$country);
				$region = json_decode($this->regionHelper->getRegionJson(),true);
				
				$countrieswithState = $region['config']['regions_required'];
				
				if(in_array($countryId,$countrieswithState)){
					$stateArr = $this->helper->getStates($countryId,$region[$countryId]);
					$regionId = array_search($userInfo['state'],$stateArr);
					$regionName=$userInfo['state'];
				}else{
					$stateArr = '';
					$regionId = '';
					$regionName = $userInfo['state'];
				}
				 $address = $this->addressDataFactory->create();
				$address->setFirstname($name[0])
					->setLastname((isset($name[1])?$name[1]:$name[0]))
					->setCountryId($countryId)
					->setRegionId($regionId)
					->setRegion($this->regionFactory->create()->setRegion($regionName)->setRegionId($regionId))
					->setCity($userInfo['city'])
					->setPostcode($userInfo['pincode'])
					->setCustomerId($customer->getId())
					->setStreet(array($userInfo['addressline1']))
					->setTelephone($userInfo['phone'])
					->setIsDefaultBilling(1)
					->setIsDefaultShipping(1);

					$this->addressRepository->save($address);
		return $customer;
	}

	protected function processCustomer($users,$offset,$limit){
		if(count($users)>0){
			foreach($users as $userData){
				$this->GenrateCustomer($userData);
			}
		}
		if(count($users)== $limit){
			$usersResp = $this->getUser($lastDate,$this->counter ,$limit);
			$this->counter += $limit;
			$this->processCustomer($lastDate,$this->counter ,$limit);
		}else{
			return true;
		}
	}
	
}