<?php

namespace FS\SSO\Cron;

use Magento\Framework\App\Action\Context;
use \Magento\Store\Model\StoreManagerInterface;

class CustomerDelete
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
		\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerFactory,
		\FS\SSO\Helper\Data  $helper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
		StoreManagerInterface $storeManager,
		\Magento\Customer\Model\CustomerFactory $customerFactory2,
       \Psr\Log\LoggerInterface $logger
		) {
		$this->_logger = $logger;
		$this->_customerFactory = $customerFactory;
        $this->_urlInterface = $urlInterface;
		$this->helper = $helper;
        $this->_objectManager = $objectManager;
		$this->storeManager     = $storeManager;
		$this->customerFactory  = $customerFactory2;
    }
	
	public function execute()
	{
		$limit = 50;
		$lastDate = $this->_customerFactory->create()
		->setOrder('modify_date', 'desc')->getFirstItem()->getModifyDate();
		
		try{
			$om = \Magento\Framework\App\ObjectManager::getInstance();
			$filesystem = $om->get('Magento\Framework\Filesystem');
			$directoryList = $om->get('Magento\Framework\App\Filesystem\DirectoryList');
			$media = $filesystem->getDirectoryRead($directoryList::MEDIA); 
			$timestamp = $media->readFile("module1/userdelete.txt");

		}catch(Exception $e){
			$timestamp = '1111111111111';
		}
		//$timestamp = file_get_contents($this->url);
		$newTime = ((!empty(trim($timestamp)))? $timestamp : '1111111111111');
		$usersResp = $this->getUser($newTime,'0',$limit);
		$this->counter= $limit;
		$users = json_decode($usersResp, true);
		$this->processCustomer($users, $this->counter, $limit);
	}
	
	protected function getUser($after, $offset,$limit){
		$parms = array("after"=>$after,"limit"=>$limit,"operation"=>"deleted");
		
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

		curl_close($ch);
		//echo "User profile=<pre>"; print_r(json_decode($server_output,true)); 
		//die;
		return $server_output;
	}

	protected function deleteUser($userInfo){
		
		$customerCollection = $this->_objectManager->create('\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory')->create();
	 	// Check for Mobile already exist 
		
		$customerCollection->addAttributeToFilter(
			array(
				array('attribute'=> 'uuid','eq' => $userInfo['user_uuid']),
				/*array('attribute'=> 'email','eq' => $userInfo['email']),
				//array('attribute'=> 'mobile','eq' => $userInfo['phone']),
				array('attribute'=> 'regenrate_token','eq' => $token_resp['refresh_token']) */
			),null,'left'
		);
		if($customerCollection->getSize()>0){
			$customer = $customerCollection->getFirstItem();
			//echo "<br>".$customer->getEmail();
			$customer->delete();
		}else{
			$this->_logger->info('\n User not found with these details'.json_encode($userInfo));
		}
		return $userInfo['timestamp'];
	}

	protected function processCustomer($users,$offset,$limit){
		$this->_logger->info('User Delete ='.count($users)." out of ".$limit);
		//echo "<pre>"; print_r($users); 
		if(count($users)>0){
			foreach($users as $userData){
				//echo "<pre>"; print_r($userData); die;
				$lastDate = $this->deleteUser($userData);
				//echo $this->url->getAbsolutePath(); die;
				try{
					$om = \Magento\Framework\App\ObjectManager::getInstance();

					$filesystem = $om->get('Magento\Framework\Filesystem');
					$directoryList = $om->get('Magento\Framework\App\Filesystem\DirectoryList');
					$media = $filesystem->getDirectoryWrite($directoryList::MEDIA);

					$media->writeFile("module1/userdelete.txt",$lastDate);
				}catch(Exception $e){
					echo $e->getMessage();
				}	
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