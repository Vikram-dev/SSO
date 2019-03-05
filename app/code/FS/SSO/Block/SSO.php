<?php

namespace FS\SSO\Block;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;

class SSO extends Template
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
        parent::_construct();
        $this->setTemplate('FS_SSO::SSO/list.phtml');
    }

	
		
}