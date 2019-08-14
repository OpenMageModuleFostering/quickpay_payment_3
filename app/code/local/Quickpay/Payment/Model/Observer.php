<?php
class Quickpay_Payment_Model_Observer
{
    public function capture($observer)
    {
		$session = Mage::getSingleton('adminhtml/session');
		
		try
		{
			$payment = $observer->getPayment()->getMethodInstance();
			
			if(get_class($payment) == "Quickpay_Payment_Model_Payment")
			{
				$invoice = $observer->getInvoice();
				Mage::helper('quickpaypayment')->capture($payment,$invoice->getGrandTotal());
				$payment->processInvoice($invoice,$payment);
			}
			else
			{
				throw new Exception(Mage::helper('quickpaypayment')->__('Ikke en Quickpay Betaling'));
			}
		}
		catch(Exception $e)
		{
			$session->addException($e, Mage::helper('quickpaypayment')->__('Ikke muligt at hæve betalingen online, grundet denne fejl: %s', $e->getMessage()));
			//throw new Exception("Failed to create Invoice on online capture");
		}
		
        return $this;
    }
		
	
	public function refund($observer)
    {
		$session = Mage::getSingleton('adminhtml/session');
		
		try
		{
			$creditmemo = $observer->getEvent()->getCreditmemo();
			$refundtotal = $creditmemo->getGrandTotal();		
			
			Mage::helper('quickpaypayment')->refund($creditmemo->getOrderId(),$refundtotal);
			
		}
		catch(Exception $e)
		{
			$session->addException($e, Mage::helper('quickpaypayment')->__('Ikke muligt at refundere betalingen online, grundet denne fejl: %s',$e->getMessage()));
		}
		return $this;
	}
	
	public function cancel($observer)
    {
		$session = Mage::getSingleton('adminhtml/session');
		
		try
		{
			$order = $observer->getEvent()->getOrder();
						
			Mage::helper('quickpaypayment')->cancel($order->getId());
			
		}
		catch(Exception $e)
		{
			$session->addException($e, Mage::helper('quickpaypayment')->__('Ikke muligt at annullerer betalingen online, grundet denne fejl: %s',$e->getMessage()));
		}
		return $this;
	}


		
	
	

	
}
