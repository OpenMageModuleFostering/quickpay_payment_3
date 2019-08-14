<?php
class Quickpay_Payment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    
    const PAYMENT_TYPE_AUTH = 'AUTHORIZATION';
    const PAYMENT_TYPE_SALE = 'SALE';

	//protected $_canCapturePartial = true;
    protected $_code  = 'quickpaypayment_payment';
    protected $_formBlockType = 'quickpaypayment/payment_form';
    protected $_infoBlockType = 'quickpaypayment/info_quickpay';
    //
    // Allowed currency types
    //
    protected $_allowCurrencyCode = array(
      'ADP','AED','AFA','ALL','AMD','ANG','AOA','ARS','AUD','AWG','AZM','BAM','BBD','BDT','BGL','BGN','BHD','BIF','BMD','BND','BOB',
      'BOV','BRL','BSD','BTN','BWP','BYR','BZD','CAD','CDF','CHF','CLF','CLP','CNY','COP','CRC','CUP','CVE','CYP','CZK','DJF','DKK',
      'DOP','DZD','ECS','ECV','EEK','EGP','ERN','ETB','EUR','FJD','FKP','GBP','GEL','GHC','GIP','GMD','GNF','GTQ','GWP','GYD','HKD',
      'HNL','HRK','HTG','HUF','IDR','ILS','INR','IQD','IRR','ISK','JMD','JOD','JPY','KES','KGS','KHR','KMF','KPW','KRW','KWD','KYD',
      'KZT','LAK','LBP','LKR','LRD','LSL','LTL','LVL','LYD','MAD','MDL','MGF','MKD','MMK','MNT','MOP','MRO','MTL','MUR','MVR','MWK',
      'MXN','MXV','MYR','MZM','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','ROL','RUB',
      'RUR','RWF','SAR','SBD','SCR','SDD','SEK','SGD','SHP','SIT','SKK','SLL','SOS','SRG','STD','SVC','SYP','SZL','THB','TJS','TMM',
      'TND','TOP','TPE','TRL','TRY','TTD','TWD','TZS','UAH','UGX','USD','UYU','UZS','VEB','VND','VUV','XAF','XCD','XOF','XPF','YER',
      'YUM','ZAR','ZMK','ZWD'
    );


	public function canRefund()
	{
		return true;
	}

    public function canEdit()
    {
        return true;
    }

    public function canCapture()
    {
        return true;
    }

    public function canUseForMultishipping()
    {
        return false;
    }

    public function getSession()
    {
        return Mage::getSingleton('quickpaypayment/session');
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

	// Calculates if integrated layout is used or not
	// If not just redirect to a page where the payment window is opened
    public function getOrderPlaceRedirectUrl()
    {
		return Mage::getUrl('quickpaypayment/payment/redirect');  
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function canUseInternal()
    {
		// Må kun bruges når der redigeres i en order
		if(Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController)
        {
			// Her kunne testes om det i forvejen er en Quickpay order.
			// med noget i stilen af Mage::getSingleton('adminhtml/session_quote')->getOrder()
			return true;
		}
        return false;
    }

	// Perhaps this can be used to make payments before the order is created
    public function createFormBlock($name)
    {
        $block = $this->getLayout()
			->createBlock('quickpaypayment/payment_form', $name)
            ->setMethod('quickpaypayment_payment')
            ->setPayment($this->getPayment())
            ->setTemplate('quickpaypayment/payment/form.phtml');

        return $block;
    }

    /*validate the currency code is avaialable to use for Quickpay or not*/
    public function validate()
    {
        parent::validate();
        
        if(!Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController)
        {
			$currency_code = $this->getQuote()->getBaseCurrencyCode();
			if (!in_array($currency_code,$this->_allowCurrencyCode)) 
			{
				Mage::throwException(Mage::helper('quickpaypayment')->__('Valutakoden (%s) er ikke kompatible med Quickpay',$currency_code));
			}
		}
        return $this;
    }

/*
    public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment)
    {
       return $this;
    }
*/

 	public function calcLanguage($lan)
    {
		$splitted = explode('_',$lan);
		return $splitted[0];
    }

    // Calculates if any of the trusted logos are to be shown - in that case return true
    public function showTrustedList() 
    {
		$logoArray = explode(',', $this->getConfigData('trustedlogos'));
		foreach($logoArray as $item) 
		{
			if ($item == 'verisign_secure' ||
				$item == 'mastercard_securecode' || 
				$item == 'pci' || 
				$item == 'nets' || 
				$item == 'euroline') 
			{
				return true;
			} 
		}
		return false;
    }
    

    // Calculates if any of the card logos are to be shown - in that case return true
    public function showCardsList() 
    {
    	$logoArray = explode(',', $this->getConfigData('cardlogos'));
		foreach($logoArray as $item) 
		{
			if ($item == 'dankort' ||
				$item == 'edankort' || 
				$item == 'danskenetbetaling' || 
				$item == 'nordea' || 
				$item == 'ewire' || 
				$item == 'forbrugsforeningen' || 
				$item == 'visa' || 
				$item == 'visaelectron' || 
				$item == 'mastercard' || 
				$item == 'maestro' || 
				$item == 'jcb' || 
				$item == 'diners' || 
				$item == 'amex') 
			{
				return true;
			} 			
		}
		return false;
    }
    

	public function canCapturePartial()
    {
		$orderid = $this->getInfoInstance()->getOrder()->getIncrementId();
		$orderid = explode("-",$orderid);
		$resource = Mage::getSingleton('core/resource');
		$table = $resource->getTableName('quickpaypayment_order_status');
		$read = $resource->getConnection('core_read');

    	$row = $read->fetchRow("SELECT cardtype FROM $table WHERE ordernum = '" . $orderid[0] . "'");
    	if($row['cardtype'] == 'dankort' || Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_CreditmemoController)
    	{
			return true;
		}
		
        return false;
    }

    public function getTitle()
    {
		// Tilføjer max beløb hvis vi er ved at ændre en order.
        if(Mage::app()->getFrontController()->getAction() instanceof Mage_Adminhtml_Sales_Order_EditController)
        {	
			$orderid = Mage::getSingleton('adminhtml/session_quote')->getOrder()->getIncrementId();
			$orderid = explode("-",$orderid);
						
			$resource = Mage::getSingleton('core/resource');
			$table = $resource->getTableName('quickpaypayment_order_status');
			$read = $resource->getConnection('core_read');
    		$row = $read->fetchRow("SELECT amount, currency FROM $table WHERE ordernum = '" . $orderid[0] . "'");
		
			return $this->getConfigData('title') . " - " . Mage::helper('quickpaypayment')->__('Maks beløb:') . " " . $row['amount']/100 . " " . $row['currency'];
		}
        return $this->getConfigData('title');
    }
    
}
