<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace FS\SSO\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use \Magento\Store\Model\StoreManagerInterface;

class Login extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $session;
	protected $_scopeConfig;
	protected $_helper;
	protected $addressDataFactory;
	protected $addressRepository;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;	
	/**
     * @var StoreManagerInterface
     */
    protected $_storeManager;
	private $cookieMetadataManager;
	private $cookieMetadataFactory;
    
	/**
     * @param Context $context
     * @param Session $customerSession
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PageFactory $resultPageFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
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
        $this->session = $customerSession;
        $this->resultPageFactory = $resultPageFactory;
		$this->regionFactory = $regionFactory;
		$this->_customerFactory = $customerFactory;
        $this->_urlInterface = $urlInterface;
		$this->helper = $helper;
        $this->addressDataFactory = $addressDataFactory;
		$this->_scopeConfig = $scopeConfig;
		$this->storeManager     = $storeManager;
		$this->customerFactory  = $customerFactory2;
		$this->addressRepository = $addressRepository;
		$this->regionHelper = $regionHelper;
        parent::__construct($context);
    }
	
	
	 /**
     * Retrieve cookie manager
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\PhpCookieManager::class
            );
        }
        return $this->cookieMetadataManager;
    }
	/**
     * Retrieve cookie metadata factory
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }
    /**
     * Customer login form page
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    
	public function execute()
    {		
		/** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
		$resultRedirect = $this->resultRedirectFactory->create();
		//echo "<pre>"; print_r($this->session->getData()); 
		//var_dump($this->session->isLoggedIn());
		//die;
		$gender=["male"=>"1","female"=>"2"];
        if ($this->session->isLoggedIn()) {
			 // if($this->session->getBeforeAuthUrl()){
				// $resultRedirect->setPath($this->session->getBeforeAuthUrl());
			// }else{
				// $resultRedirect->setPath('*/*/');
			// }
			$resultRedirect->setPath('/customer/account/edit');
            return $resultRedirect;
        }else{
			
			$redirect=$this->_urlInterface->getUrl('customer/account/login/'); 
			$phrase = $this->_scopeConfig->getValue("sso/general/api_phrase"); 
			
			/* if($this->getRequest()->getParam('error_description')){
				echo $this->getRequest()->getParam('error_description'); die;
			} */
			
			if($this->getRequest()->getParam('code')){
				if($this->getRequest()->getParam('state')!==$phrase){
					//mail();
					echo "You are not authorized user, This operation will be reported to Admin<br> IP address=".$_SERVER['REMOTE_ADDR'];
					if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)){
						echo "<br>ISP Address=".$_SERVER['HTTP_X_FORWARDED_FOR'];
					}
					if(array_key_exists('HTTP_CLIENT_IP', $_SERVER)){
						echo "<br>Client IP=".$_SERVER['HTTP_CLIENT_IP'];
					}
					die;
				}else{
					$resp =  $this->helper->getToken($this->getRequest()->getParam('code'),$redirect);
					$token_resp = json_decode($resp,true);
					if($token_resp['access_token']){
						//echo "<pre>";print_r($token_resp); die;
						$userResp = json_decode($this->getUser($token_resp['access_token'],$redirect),true);
						if((!empty($userResp['uuid']))&&(!empty($userResp['email']))){
							//echo "<pre>";print_r($userResp);  die;
							$customereCollection = $this->_customerFactory->create();
							/* $customereCollection->addFieldToFilter('email',$userResp['email']);
							$customereCollection->addFieldToFilter('mobile',$userResp['phone']);
							$customereCollection->addFieldToFilter('uuid',$userResp['uuid']); */

							 $customereCollection->addAttributeToFilter(
								array(
									array('attribute'=> 'email','eq' => $userResp['email']),
									//array('attribute'=> 'mobile','eq' => $userResp['phone']),
									array('attribute'=> 'uuid','eq' => $userResp['uuid']),	
									//array('attribute'=> 'regenrate_token','eq' => $token_resp['refresh_token'])
								),null,'left'
							);
							//echo $customereCollection->getSelect()->assemble(); die;
							 
							if(count($customereCollection->getData()) < 1){
								$customer = $this->GenrateCustomer($userResp);
							}else{
								$customer = $customereCollection->getFirstItem();
								//echo "<pre>"; print_r($customer->getData()); die;
							}
							//echo "<pre>"; print_r($userResp); die;
								$customerData  = $customer->getDataModel();
								$customerData->setCustomAttribute('mobile',$userResp['phone']);
								$customerData->setCustomAttribute('uuid',$userResp['uuid']);
								$customerData->setCustomAttribute('regenrate_token',$token_resp['refresh_token']);
								$customer->updateData($customerData);
								$customer->setGender($gender[strtolower($userResp['gender'])])->save();
							/* echo "<pre>"; print_r($customer->getData());
							print_r($userResp); die; */
								$this->session->setCustomerAsLoggedIn($customer);
								$this->session->regenerateId();
								$this->session->setToken($token_resp['access_token']);
								if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
									$metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
									$metadata->setPath('/');
									$this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
								}
								$resultRedirect->setUrl($this->_urlInterface->getUrl('customer/account/edit'));
								return $resultRedirect;
								
								/* $this->session->login($userResp['email']);
								$this->session->isLoggedIn(true); */
								//return $this->resultRedirectFactory->create()->setUrl('customer/account/edit');
							
						}
					}
				}
			}
			
			$api = $this->helper->APICr();
			
			$url = $api['URL']."oauth/authorize?client_id=".$api['cleint_id']."&scope=profile&state=".$phrase."&response_type=code&redirect_uri=".$redirect;
			
			$resultRedirect->setPath($url);
            return $resultRedirect;
		}

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setHeader('Login-Required', 'true');
        return $resultPage;
    }
	
	
	protected function getUser($Auth,$redirect){
		$parms = json_encode(array("grant_type"=>"authorization_code","redirect_uri"=>$redirect));
		
		$api = $this->helper->APICr();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$api['URL']."user-management/user/profile");
		//curl_setopt($ch, CURLOPT_POST, 1);
		//curl_setopt($ch, CURLOPT_POSTFIELDS,$parms);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type:application/json','Authorization:Bearer '.$Auth
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
		// $customerCollection->addFieldToFilter('mobile',$userInfo["phone"]);
		$customerCollection->addFieldToFilter('email',$userInfo["email"]);
			
			if($customerCollection->getSize()>0){
				$customerRec = $customerCollection->getFirstItem();// die;
				$customerRec->setMobile($userInfo['phone'])->setGender($gender[strtolower($userInfo['gender'])])->setUuid($userInfo['uuid'])->save();
				
				return $customerRec;
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
				/* $customerData  = $customer->getDataModel();
				$customerData->setCustomAttribute('modify_date',$userInfo['lastModifiedDate']);
				$customer->updateData($customerData); */
				$customer->save();
				
				$countryId = array_search($userInfo['country'],$country);
				$region = json_decode($this->regionHelper->getRegionJson(),true);
				
				$countrieswithState = $region['config']['regions_required'];
				
				//echo "<pre> $countryId="; print_r($countrieswithState); die;
				if(in_array($countryId,$countrieswithState)){
					$stateArr = $this->helper->getStates($countryId,$region[$countryId]);
					$regionId = array_search($userInfo['state'],$stateArr);
					$regionName=$userInfo['state'];
				}else{
					$stateArr = '';
					$regionId = '';
					$regionName = $userInfo['state'];
				}
				//echo "<pre> state=".$regionName; print_r($stateArr); die;
				
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
					//->setSaveInAddressBook('1')
					->setIsDefaultShipping(1);
				//echo "<pre>"; print_r($address->getData()); die;
				/* try{
					$address->save();
				}catch (Exception $e) {
                      Zend_Debug::dump($e->getMessage());
					  echo $e->getMessage(); die;
                 } */
					$this->addressRepository->save($address);

				// Save data
				//$customer->sendNewAccountEmail();
				return $customer;
			}
		
	}
}
