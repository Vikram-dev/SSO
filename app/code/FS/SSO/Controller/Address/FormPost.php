<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace FS\SSO\Controller\Address;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Model\Address\Mapper;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Customer\Model\Session;
use Magento\Directory\Helper\Data as HelperData;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\View\Result\PageFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FormPost extends \Magento\Customer\Controller\Address
{
    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var Mapper
     */
    private $customerAddressMapper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param FormKeyValidator $formKeyValidator
     * @param FormFactory $formFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressInterfaceFactory $addressDataFactory
     * @param RegionInterfaceFactory $regionDataFactory
     * @param DataObjectProcessor $dataProcessor
     * @param DataObjectHelper $dataObjectHelper
     * @param ForwardFactory $resultForwardFactory
     * @param PageFactory $resultPageFactory
     * @param RegionFactory $regionFactory
     * @param HelperData $helperData
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        FormKeyValidator $formKeyValidator,
        FormFactory $formFactory,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
        AddressRepositoryInterface $addressRepository,
        AddressInterfaceFactory $addressDataFactory,
        RegionInterfaceFactory $regionDataFactory,
        DataObjectProcessor $dataProcessor,
        DataObjectHelper $dataObjectHelper,
        ForwardFactory $resultForwardFactory,
        PageFactory $resultPageFactory,
        RegionFactory $regionFactory,
        HelperData $helperData,
		\FS\SSO\Helper\Data $ssoHelper
    ) {
        $this->regionFactory = $regionFactory;
		$this->customerFactory = $customerFactory;
        $this->helperData = $helperData;
		$this->ssoHelper = $ssoHelper;
        parent::__construct(
            $context,
            $customerSession,
            $formKeyValidator,
            $formFactory,
            $addressRepository,
            $addressDataFactory,
            $regionDataFactory,
            $dataProcessor,
            $dataObjectHelper,
            $resultForwardFactory,
            $resultPageFactory
        );
    }

    /**
     * Extract address from request
     *
     * @return \Magento\Customer\Api\Data\AddressInterface
     */
    protected function _extractAddress()
    {
        $existingAddressData = $this->getExistingAddressData();

        /** @var \Magento\Customer\Model\Metadata\Form $addressForm */
        $addressForm = $this->_formFactory->create(
            'customer_address',
            'customer_address_edit',
            $existingAddressData
        );
        $addressData = $addressForm->extractData($this->getRequest());
        $attributeValues = $addressForm->compactData($addressData);

        $this->updateRegionData($attributeValues);

        $addressDataObject = $this->addressDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $addressDataObject,
            array_merge($existingAddressData, $attributeValues),
            \Magento\Customer\Api\Data\AddressInterface::class
        );
        $addressDataObject->setCustomerId($this->_getSession()->getCustomerId())
            ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
            ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));

        return $addressDataObject;
    }

    /**
     * Retrieve existing address data
     *
     * @return array
     * @throws \Exception
     */
    protected function getExistingAddressData()
    {
        $existingAddressData = [];
        if ($addressId = $this->getRequest()->getParam('id')) {
            $existingAddress = $this->_addressRepository->getById($addressId);
            if ($existingAddress->getCustomerId() !== $this->_getSession()->getCustomerId()) {
                throw new \Exception();
            }
            $existingAddressData = $this->getCustomerAddressMapper()->toFlatArray($existingAddress);
        }
        return $existingAddressData;
    }

    /**
     * Update region data
     *
     * @param array $attributeValues
     * @return void
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function updateRegionData(&$attributeValues)
    {
        if (!empty($attributeValues['region_id'])) {
            $newRegion = $this->regionFactory->create()->load($attributeValues['region_id']);
            $attributeValues['region_code'] = $newRegion->getCode();
            $attributeValues['region'] = $newRegion->getDefaultName();
        }

        $regionData = [
            RegionInterface::REGION_ID => !empty($attributeValues['region_id']) ? $attributeValues['region_id'] : null,
            RegionInterface::REGION => !empty($attributeValues['region']) ? $attributeValues['region'] : null,
            RegionInterface::REGION_CODE => !empty($attributeValues['region_code'])
                ? $attributeValues['region_code']
                : null,
        ];

        $region = $this->regionDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $region,
            $regionData,
            \Magento\Customer\Api\Data\RegionInterface::class
        );
        $attributeValues['region'] = $region;
    }

    /**
     * Process address form save
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
		//echo "<pre>"; print_r($this->_getSession()->getCustomerId()); die;
        $redirectUrl = null;
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        if (!$this->getRequest()->isPost()) {
            $this->_getSession()->setAddressFormData($this->getRequest()->getPostValue());
            return $this->resultRedirectFactory->create()->setUrl(
                $this->_redirect->error($this->_buildUrl('*/*/edit'))
            );
        }
		
		/************* Verify and re-generate token ***************/
		
		$customer = $this->customerFactory->create()->load($this->_getSession()->getCustomerId()); 		
		$token = $this->_getSession()->getToken(); 
		$resp = $this->ssoHelper->verifyToken($token);
		$validate_resp = json_decode($resp,true);
		if(array_key_exists('error',$validate_resp)){
			/* Token not valid, So re-generate this */
			$regenrate_resp = $this->ssoHelper->getToken('',$redirect,$customer->getRegenrateToken()); 
			$newtoken=json_decode($regenrate_resp,true);
			$token = $newtoken['access_token'];
		}
		
        try {
            $address = $this->_extractAddress();
			$country = $this->ssoHelper->getAvailableCountries();
			$gender=["male"=>"1","female"=>"2"];
			$street=$this->getRequest()->getParam('street');
			$custData = [
						"name"=> $customer['firstname']." ".$customer['lastname'],
						"email"=> $customer['email'],
						"gender"=> array_search($customer['gender'],$gender),
						"phone_no"=> $customer['mobile'],
						"addressline1" => implode(",",$street),
						"state" =>$this->getRequest()->getParam('region'),
						"streetAddress" => implode(",",$street),
						"city" => $this->getRequest()->getParam('city'),
						"pincode" =>$this->getRequest()->getParam('postcode'),
						"country" =>$country[$this->getRequest()->getParam('country_id')]
						];
			//echo "<pre>"; print_r($custData); die;
			$token = $this->_getSession()->getToken();
			$response = $this->ssoHelper->updateUser($token,$custData);
			//echo "Response = ".$response; die;
			if($response!='success'){
				$redirectUrl = $this->_buildUrl('*/*/index');
				$this->messageManager->addException($e, __('We can\'t save the address due to Api is down.'));
			}
            $this->_addressRepository->save($address);
            $this->messageManager->addSuccess(__('You saved the address.'));
            $url = $this->_buildUrl('*/*/index', ['_secure' => true]);
            return $this->resultRedirectFactory->create()->setUrl($this->_redirect->success($url));
        } catch (InputException $e) {
            $this->messageManager->addError($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addError($error->getMessage());
            }
        } catch (\Exception $e) {
            $redirectUrl = $this->_buildUrl('*/*/index');
            $this->messageManager->addException($e, __('We can\'t save the address.'));
        }

        $url = $redirectUrl;
        if (!$redirectUrl) {
            $this->_getSession()->setAddressFormData($this->getRequest()->getPostValue());
            $url = $this->_buildUrl('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }

        return $this->resultRedirectFactory->create()->setUrl($this->_redirect->error($url));
    }

    /**
     * Get Customer Address Mapper instance
     *
     * @return Mapper
     *
     * @deprecated 100.1.3
     */
    private function getCustomerAddressMapper()
    {
        if ($this->customerAddressMapper === null) {
            $this->customerAddressMapper = ObjectManager::getInstance()->get(
                \Magento\Customer\Model\Address\Mapper::class
            );
        }
        return $this->customerAddressMapper;
    }
}
