<?php

namespace FS\SSO\Cron;

use Magento\Framework\App\Action\Context;
use \Magento\Store\Model\StoreManagerInterface;

class CustomerSync 
{
	protected $_scopeConfig;
	protected $_helper;
	protected $_logger;
	protected $addressDataFactory;
	protected $addressRepository;
	protected $_storeManager;
	protected $counter;
	 protected $_objectManager;
	 
   public function __construct(
   Context $context,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory,	
		\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerFactory,
		\FS\SSO\Helper\Data  $helper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
		StoreManagerInterface $storeManager,
		\Magento\Customer\Model\CustomerFactory $customerFactory2,
		\Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
		\Magento\Customer\Api\Data\RegionInterfaceFactory $regionFactory,
       \Psr\Log\LoggerInterface $logger,
		\Magento\Directory\Helper\Data	$regionHelper
		) {
		$this->_logger = $logger;
		$this->regionFactory = $regionFactory;
		$this->_customerFactory = $customerFactory;
        $this->_urlInterface = $urlInterface;
		$this->helper = $helper;
        $this->_objectManager = $objectManager;
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
		$usersResp = $this->getUser('1111111111111','0',$limit);
		$this->counter= $limit;
		$users = json_decode($usersResp, true);
		$this->processCustomer($users, $this->counter, $limit);
	}
	
	protected function getUser($after, $offset,$limit){
		$parms = array("after"=>$after,"limit"=>$limit,"operation"=>"added");
		
		$api = $this->helper->APICr();
		//echo $api['URL']."user-management/users"; die;
		
		 $Auth = base64_encode($api['cleint_id'].":".$api['cleint_secret']); 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$api['URL']."user-management/users?".http_build_query($parms));
		//curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );
		//curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parms));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Basic '.$Auth
		));

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
		
		//return $userInfo['lastModifiedDate'];
		//echo "<pre>"; print_r($userInfo); die; 
		$gender=["male"=>"1","female"=>"2"];
		$country = $this->helper->getAvailableCountries();
		$customerCollection = $this->_objectManager->create('\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory')->create();
	 	// Check for Mobile already exist 
		
		$customerCollection->addAttributeToFilter(
			array(
				array('attribute'=> 'email','eq' => $userInfo['email']),
				//array('attribute'=> 'mobile','eq' => $userInfo['phone']),
				/* array('attribute'=> 'uuid','eq' => $userInfo['uuid']),	
				array('attribute'=> 'regenrate_token','eq' => $token_resp['refresh_token']) */
			),null,'left'
		);
			
			if(
			empty(trim($userInfo['name'])) || 
			empty(trim($userInfo['email']))
			){ 
				$this->_logger->info('User data missing mandatory fields ='.json_encode($userInfo));
				return $userInfo['lastModifiedDate']; }
			
			if($customerCollection->getSize()>0){
				$customer = $customerCollection->getFirstItem();// die;
				$name = explode(" ",$userInfo['name']);
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
				  
				
			}
				$customerData  = $customer->getDataModel();
				$customerData->setCustomAttribute('modify_date',$userInfo['lastModifiedDate']);
				$customer->updateData($customerData);
				$customer->save();
				$countryId = array_search($userInfo['country'],$country);
				$region = json_decode($this->regionHelper->getRegionJson(),true);
				
				$countrieswithState = $region['config']['regions_required'];
				if(empty($userInfo['addressline1'])){
					$street= array("empty");
				}else{
					$street= array($userInfo['addressline1']);
				}
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
					->setStreet($street)
					->setTelephone($userInfo['phone'])
					->setIsDefaultBilling(1)
					->setIsDefaultShipping(1);
			try{
				$this->addressRepository->save($address);
			}catch(\Exception $e){
				$this->_logger->info('User address data missing mandatory fields ='.json_encode($userInfo));
				return $userInfo['lastModifiedDate'];
			}
		return $userInfo['lastModifiedDate'];
	}

	protected function processCustomer($users,$offset,$limit){
		$this->_logger->info('User data push='.count($users)." out of ".$limit);
		//echo "<pre>"; print_r($users); 
		if(count($users)>0){
			foreach($users as $userData){
				//echo "<pre>"; print_r($userData); die;
				$lastDate = $this->GenrateCustomer($userData);
			}
		}
		if(count($users)== $limit){
			$usersResp = $this->getUser($lastDate,$this->counter ,$limit);
			$this->counter += $limit;
			$this->processCustomer(json_decode($usersResp,true),$this->counter ,$limit);
		}else{
			return true;
		}
	}
	
}