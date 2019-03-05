<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace FS\SSO\Controller\Checkout;
//use \FS\SSO\Helper\Data;

class Index extends \Magento\Checkout\Controller\Index\Index
{
    /**
     * Checkout page
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */

	 
    public function execute()
    {
		if(!$this->_customerSession->isLoggedIn()){
			$urlInterface = $this->_objectManager->get(\Magento\Framework\UrlInterface::class);
			$redirect=$urlInterface->getUrl('customer/account/login/'); 
			
			$ssoHelper = $this->_objectManager->get(\FS\SSO\Helper\Data::class);
			$api = $ssoHelper->APICr();
			//echo "<pre>"; print_r($api); die;
			$url = $api['URL']."oauth/authorize?client_id=".$api['cleint_id']."&scope=profile&state=".$api['phrase']."&response_type=code&redirect_uri=".$redirect;
			
			return $this->resultRedirectFactory->create()->setPath($url);
		}
        /** @var \Magento\Checkout\Helper\Data $checkoutHelper */
        $checkoutHelper = $this->_objectManager->get(\Magento\Checkout\Helper\Data::class);
        if (!$checkoutHelper->canOnepageCheckout()) {
            $this->messageManager->addError(__('One-page checkout is turned off.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $quote = $this->getOnepage()->getQuote();
        if (!$quote->hasItems() || $quote->getHasError() || !$quote->validateMinimumAmount()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        if (!$this->_customerSession->isLoggedIn() && !$checkoutHelper->isAllowedGuestCheckout($quote)) {
            $this->messageManager->addError(__('Guest checkout is disabled.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $this->_customerSession->regenerateId();
        $this->_objectManager->get(\Magento\Checkout\Model\Session::class)->setCartWasUpdated(false);
        $this->getOnepage()->initCheckout();
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Checkout'));
        return $resultPage;
    }
}
