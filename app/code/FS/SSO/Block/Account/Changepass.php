<?php

namespace FS\SSO\Block\Account;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;

class Changepass extends Template
{
    public $collectionFactory;
    
    public function __construct(
        CollectionFactory $collectionFactory,
        Context $context,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }
	
	protected function _construct()
    {
		echo "Block"; die;
        parent::_construct();
        $this->setTemplate('FS_SSO::account/change_pass.phtml');
    }

	
		
}