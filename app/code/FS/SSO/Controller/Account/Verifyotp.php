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

class Verifyotp extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Session
     */
    	protected $session;
		protected $_helper;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PageFactory $resultPageFactory,
		\Magento\Customer\Model\CustomerFactory $customerFactory2,
		\FS\SSO\Helper\Data  $helper
		
    ) {
        $this->session = $customerSession;
        $this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;
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
		$api = $this->helper->APICr();
		
		//echo "<pre>"; print_r($this->getRequest()->getParams()); die;
		if($this->getRequest()->getParam('state')==$api['phrase']){
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
				$regenrate_resp = $this->helper->getToken('','',$customer->getRegenrateToken()); 
				$newtoken=json_decode($regenrate_resp,true);
				$this->session->setToken($newtoken['access_token']);
				$resultRedirect->setPath('/customer/account/edit');
				return $resultRedirect;
			}elseif($this->getRequest()->getParam('otp')){
				$mobile = $this->getRequest()->getParam('mobile');
				$otp = $this->getRequest()->getParam('otp');
				$response = $this->helper->verifyOTP($token,$mobile,$otp);
				if(strpos(strtolower($response),'error')){
					$output = ['success' => false,'message' =>'OTP expired or you have entered wrong OTP.'];
				}elseif(strpos(strtolower($response),'success')){
					$output = ['success' => true,'message' =>'OTP is successfully verified. Now please click on save button.'];
				}
			}elseif($this->getRequest()->getParam('mobile')){
				$mobile = $this->getRequest()->getParam('mobile');
				$response = $this->helper->generateOTP($token,$mobile);
				if(strpos(strtolower($response),'error')){
					$output = ['success' => false,'message' =>'Not able to send OTP. Please contact admin.'];
				}elseif(strpos(strtolower($response),'success')){
					$output = ['success' => true,'message' =>'OTP sent it to your given mobile number.'];
				}
			}
		}else{
			$output = ['success' => false,'message' =>'Session time out, please re-login and try again.'];
		}
		return $this->getResponse()->representJson($this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode($output));	
    }
	
	
}
