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
use Magento\Customer\Api\Data\AddressInterfaceFactory;

class Changepass extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    protected $session;
	protected $_scopeConfig;
	protected $_helper;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
	/**
     * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
     */
    protected $addressDataFactory;
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
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlInterface,
		\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerFactory,
		\Magento\Customer\Model\CustomerFactory $customerFactory2,
		\FS\SSO\Helper\Data  $helper
    ) {
        $this->session = $customerSession;
        $this->resultPageFactory = $resultPageFactory;
        $this->_urlInterface = $urlInterface;
		$this->storeManager = $storeManager;
		$this->helper = $helper;
		$this->_scopeConfig = $scopeConfig;
        $this->_customerFactory = $customerFactory;
		$this->customerFactory  = $customerFactory2;
        parent::__construct($context);
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
			$redirect=$this->_urlInterface->getUrl('sso/account/changepass/');
			$phrase = $this->_scopeConfig->getValue("sso/general/api_phrase");
			
			$api = $this->helper->APICr();
			
		//echo "<pre>"; print_r($this->getRequest()->getParams()); die;
		if($this->getRequest()->getParam('state')==$phrase){
			$this->session->logout();
			//$resultRedirect->setPath('*/*/');
			//echo $this->storeManager->getStore()->getBaseUrl(); die;
			$resultRedirect->setPath($this->storeManager->getStore()->getBaseUrl());
			return $resultRedirect;
		}
		
        if ($this->session->isLoggedIn()) {
			$token = $this->session->getToken(); 
			$resp = $this->helper->verifyToken($token);
			$validate_resp = json_decode($resp,true);
			if(array_key_exists('error',$validate_resp)){
				/* Token not valid */
				$apik = $this->helper->APICr(); 
				$customer = $this->customerFactory->create()->load($this->session->getCustomerId());
				//echo "<pre>UUid=".$customer->getUuid().", Regenrate Token=".$customer->getRegenrateToken().", Gender=".$customer->getGender(); 
				//echo "<br><pre>"; print_r($api); die;
				//die;
				//$this->session->getCustomerId();
				$regenrate_resp = $this->helper->getToken('',$redirect,$customer->getRegenrateToken()); 
				$newtoken=json_decode($regenrate_resp,true);
				$newurl = $api['URL']."user-management/user/change-password?client_id=".$api['cleint_id']."&access_token=".$newtoken['access_token']."&state=".$phrase."&redirect_uri=".$redirect; 
				
				$resultRedirect->setPath($newurl);
				return $resultRedirect;
			}
			if($validate_resp['user_uuid'] && ($validate_resp['client_id']==$api['cleint_id'])){
				/* user-management/user/change-password */
				
				$url = $api['URL']."user-management/user/change-password?client_id=".$api['cleint_id']."&state=".$phrase."&access_token=".$token."&redirect_uri=".$redirect; 
			
				$resultRedirect->setPath($url);
				return $resultRedirect;
			}
				
            return $resultRedirect;
		}

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setHeader('Change Password', 'true');
        return $resultPage;
    }
	
	
}
