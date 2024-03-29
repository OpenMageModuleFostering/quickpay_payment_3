<?php
class Quickpay_Payment_Block_Info_Quickpay extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('quickpaypayment/info/default.phtml');
    
    }

    public function getInfo()
    {
        $info = $this->getData('info');
        if (!($info instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Betalings objektet kan ikke hente information'));
        }
        return $info;
    }

    public function getQuickpayInfoHtml()
    {
    	$res = "";
    	if($this->getInfo()->getOrder())
    	{
			$read = Mage::getSingleton('core/resource')->getConnection('core_read');
			$row = $this->paymentData = $read->fetchRow("select * from quickpaypayment_order_status where ordernum = " . $this->getInfo()->getOrder()->getIncrementId());
		   	
		   	if(is_array($row))
		   	{
				if ($row['status'] >= '1') 
				{
					$res .= "<table border='0'>";
					if ($row['transaction'] != '0') {
						$res .= "<tr><td>" . $this->__('Transaktions ID:') . "</td>";
						$res .= "<td>" . $row['transaction'] . "</td></tr>";
					}
					
					if ($row['cardtype'] != '0') {
						$res .= "<tr><td>" . $this->__('Korttype:') . "</td>";
						$res .= "<td>" . $row['cardtype'] . "</td></tr>";
					}
					if ($row['currency'] != '0') {
						$res .= "<tr><td>" . $this->__('Valuta:') . "</td>";
						$res .= "<td>" . $row['currency'] . "</td></tr>";
					}
					
					$res .= "</table><br>";	
				} 
				else
				{
					$res .= "<br>" . $this->__('Der er endnu ikke registreret nogen betaling for denne ordre!') . "<br>";
				}	
			}	
		}
	   	
	   	return $res;
    }

    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }
    
    public function toPdf()
    {
        $this->setTemplate('payment/info/pdf/default.phtml');
        return $this->toHtml();
    }
}
