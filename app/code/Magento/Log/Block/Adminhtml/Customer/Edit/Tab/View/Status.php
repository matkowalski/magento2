<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Log\Block\Adminhtml\Customer\Edit\Tab\View;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Class Status
 * @package Magento\Log\Block\Adminhtml\Customer\Edit\Tab\View
 */
class Status extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface
     */
    protected $customer;

    /**
     * @var \Magento\Log\Model\Customer
     */
    protected $customerLog;

    /**
     * @var \Magento\Log\Model\Visitor
     */
    protected $modelLog;

    /**
     * @var \Magento\Log\Model\CustomerFactory
     */
    protected $logFactory;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Log\Model\CustomerFactory $logFactory
     * @param \Magento\Log\Model\Log $modelLog
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory
     * @param \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Log\Model\CustomerFactory $logFactory,
        \Magento\Log\Model\Log $modelLog,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        array $data = []
    ) {
        $this->logFactory = $logFactory;
        $this->modelLog = $modelLog;
        $this->dateTime = $dateTime;
        $this->customerFactory = $customerFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getStoreLastLoginDateTimezone()
    {
        return $this->_scopeConfig->getValue(
            $this->_localeDate->getDefaultTimezonePath(),
            \Magento\Framework\Store\ScopeInterface::SCOPE_STORE,
            $this->getCustomer()->getStoreId()
        );
    }

    /**
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    public function getCustomer()
    {
        if (!$this->customer) {
            $this->customer = $this->customerFactory->create();
            $this->dataObjectHelper
                ->populateWithArray($this->customer, $this->_backendSession->getCustomerData()['account']);
        }
        return $this->customer;
    }

    /**
     * Get customer's current status
     *
     * @return \Magento\Framework\Phrase
     */
    public function getCurrentStatus()
    {
        $log = $this->getCustomerLog();
        $interval = $this->modelLog->getOnlineMinutesInterval();
        if ($log->getLogoutAt() ||
            strtotime($this->dateTime->now()) - strtotime($log->getLastVisitAt()) > $interval * 60
        ) {
            return __('Offline');
        }
        return __('Online');
    }

    /**
     * Get customer last login date
     *
     * @return \Magento\Framework\Phrase|string
     */
    public function getLastLoginDate()
    {
        $date = $this->getCustomerLog()->getLoginAt();
        if ($date) {
            return $this->formatDate($date, TimezoneInterface::FORMAT_TYPE_MEDIUM, true);
        }
        return __('Never');
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getStoreLastLoginDate()
    {
        $date = $this->getCustomerLog()->getLoginAtTimestamp();
        if ($date) {
            $date = $this->_localeDate->scopeDate($this->getCustomer()->getStoreId(), $date, true);
            return $this->formatDate($date, TimezoneInterface::FORMAT_TYPE_MEDIUM, true);
        }
        return __('Never');
    }

    /**
     * Load Customer Log model
     *
     * @return \Magento\Log\Model\Customer
     */
    public function getCustomerLog()
    {
        if (!$this->customerLog) {
            $this->customerLog = $this->logFactory->create()->loadByCustomer($this->getCustomer()->getId());
        }
        return $this->customerLog;
    }
}
