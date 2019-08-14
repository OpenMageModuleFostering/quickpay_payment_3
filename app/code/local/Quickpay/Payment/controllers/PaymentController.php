<?php
class Quickpay_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
    // Flag only used for callback
    protected $_callbackAction = false;
    
    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }
    
    public function getPayment()
    {
        return Mage::getSingleton('quickpaypayment/payment');
    }

    public function redirectAction()
    {
    	$incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

		$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
		$order->addStatusToHistory(
			Mage::getModel('quickpaypayment/payment')->getConfigData('order_status_before_payment'),
			$this->__("Ordren er oprettet og afventer betaling.")
		);

		$order->save();

    	if((int)Mage::getStoreConfig('cataloginventory/options/can_subtract') == 1 && (int)Mage::getStoreConfig('cataloginventory/item_options/manage_stock') == 1)
		{
			$resource = Mage::getSingleton('core/resource');
			$connection = $resource->getConnection('core_read');
			$table = $resource->getTableName('quickpaypayment_order_status');
			// Tester om varene er trukket allerede.
			if(count($connection->fetchAll("SELECT id FROM $table WHERE ordernum = '$incrementId'")) == 0)
			{
				$this->addToStock($incrementId);
			}
		}

		$block = Mage::getSingleton('core/layout')->createBlock('quickpaypayment/payment_redirect');
		$block->toHTML();
    }
    
    public function addToStock($incrementId)
    {
		$payment = Mage::getModel('quickpaypayment/payment');
		$session = Mage::getSingleton('checkout/session');
		
		if (((int)$payment->getConfigData('handlestock')) == 1) 
		{
			if(!isset($_SESSION['stock_removed']) || $_SESSION['stock_removed'] != $session->getLastRealOrderId()) 
			{
				$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
				$items = $order->getAllItems(); // Get all items from the order
				if ($items) 
				{
					foreach($items as $item) 
					{
						$quantity = $item->getQtyOrdered(); // get Qty ordered
						$product_id = $item->getProductId(); // get it's ID

						$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id); // Load the stock for this product
						$stock->setQty($stock->getQty()+$quantity); // Set to new Qty
						$stock->setIsInStock(true);    
						$stock->save(); // Save                       
					}
				} 

				//
				// Flag so that stock is only updated once!
				//
				$_SESSION['stock_removed'] = $session->getLastRealOrderId();
			}
		}
    }
    
    public function removeFromStock($incrementId)
    {
		$payment = Mage::getModel('quickpaypayment/payment');
		$session = Mage::getSingleton('checkout/session');

		if (((int)$payment->getConfigData('handlestock')) == 1) 
		{
			$order = Mage::getModel('sales/order')->load($incrementId);
			$items = $order->getAllItems(); // Get all items from the order
			if($items) 
			{
				foreach($items as $item) 
				{
					$quantity = $item->getQtyOrdered(); // get Qty ordered
					$product_id = $item->getProductId(); // get it's ID

					$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id); // Load the stock for this product
					$stock->setQty($stock->getQty()-$quantity); // Set to new Qty            
					$stock->save(); // Save
				}
			}            
		}
    }

    public function cancelAction()
    {
        //$session = Mage::getSingleton('checkout/session');
		//$lastQuoteId = $session->getLastQuoteId();
        //$session->unsetAll();
        //$session->setQuoteId($lastQuoteId);
        //$quote = Mage::getModel('sales/quote')->load($session->getLastQuoteId());        
        //$session->replaceQuote($quote);
        
		//var_dump($session->getQuote()->getData());
        //$session->setCartWasUpdated(true);
        
        //$quote = Mage::getModel('sales/quote')->load($session->getLastQuoteId());
        //$session->replaceQuote($quote);
        
        $this->_redirect('checkout/cart');
     }

    public function successAction()
    {   
		$order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
		
		$payment = Mage::getModel('quickpaypayment/payment');
		$order->addStatusToHistory($payment->getConfigData('order_status'));
		$order->save(); 
		
		// CREATES INVOICE if payment instantcapture is ON
		if((int)$payment->getConfigData('instantcapture') == 1 && (int)$payment->getConfigData('instantinvoice') == 1)
		{
			if($order->canInvoice()) 
			{
				$invoice = $order->prepareInvoice();
				$invoice->register();
				
				Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();
				
				$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_COMPLETE);
				$order->save();
			}
		}
		
		$this->_redirect('checkout/onepage/success');
    }
    
    public function callbackAction()
    {
        $session = Mage::getSingleton('checkout/session');
           
        $payment = Mage::getModel('quickpaypayment/payment');
        
		$ourCheck = md5(
			$_POST['msgtype'].
			$_POST['ordernumber'].
			$_POST['amount']. 
			$_POST['currency']. 
			$_POST['time']. 
			$_POST['state']. 
			$_POST['qpstat']. 
			$_POST['qpstatmsg']. 
			$_POST['chstat'].
			$_POST['chstatmsg']. 
			$_POST['merchant']. 
			$_POST['merchantemail'].
			$_POST['transaction']. 
			$_POST['cardtype']. 
			$_POST['cardnumber']. 
			$_POST['splitpayment']. 
			$_POST['fraudprobability']. 
			$_POST['fraudremarks']. 
			$_POST['fraudreport']. 
			$_POST['fee']. 
			$payment->getConfigData('md5secret')
		);
		if($_POST['md5check'] == $ourCheck)
		{
			$order = Mage::getModel('sales/order')->loadByIncrementId((int)$_POST["ordernumber"]);

			if((int)$payment->getConfigData('transactionfee') == 1)
			{
				$fee = ((int)$_POST['fee']/100);
				$fee_text = "";
				if((int)$payment->getConfigData('specifytransactionfee') == 1)
				{
					$fee_text = " " . Mage::helper('quickpaypayment')->__("inkl. %s %s i transaktionsgebyr",$fee,$order->getData('order_currency_code'));
				}
				
				$order->setShippingDescription($order->getShippingDescription() . $fee_text);
				$order->setShippingAmount($order->getShippingAmount() + $fee);	
				$order->setBaseShippingAmount($order->getShippingAmount());
				$order->setGrandTotal($order->getGrandTotal() + $fee);
				$order->setBaseGrandTotal($order->getGrandTotal());
				$order->save();
				
			}
			
			
			// Save the order into the quickpaypayment_order_status table
			// IMPORTANT to update the status as 1 to ensure that the stock is handled correctly!
			// TODO: We sould use quickpays md5check to see if the data have been altered..

			if($_POST['qpstat'] == "000")
			{
				$resource = Mage::getSingleton('core/resource');
				$table = $resource->getTableName('quickpaypayment_order_status');
				
				$query = "UPDATE $table SET " . 
						'transaction = "' 		. ((isset($_POST['transaction'])) 		? $_POST['transaction'] 		: '') . '", ' .
						'status = "'			. ((isset($_POST['state'])) 			? $_POST['state'] 				: '') . '", ' .
						'pbsstat = "'			. ((isset($_POST['pbsstat'])) 			? $_POST['pbsstat'] 			: '') . '", ' .
						'qpstat = "'			. ((isset($_POST['qpstat'])) 			? $_POST['qpstat'] 				: '') . '", ' .
						'qpstatmsg = "'			. ((isset($_POST['qpstatmsg'])) 		? $_POST['qpstatmsg'] 			: '') . '", ' .
						'chstat = "'			. ((isset($_POST['chstat'])) 			? $_POST['chstat'] 				: '') . '", ' .
						'chstatmsg = "'			. ((isset($_POST['chstatmsg'])) 		? $_POST['chstatmsg'] 			: '') . '", ' .
						'merchantemail = "'		. ((isset($_POST['merchantemail'])) 	? $_POST['merchantemail'] 		: '') . '", ' .
						'merchant = "'			. ((isset($_POST['merchant'])) 			? $_POST['merchant'] 			: '') . '", ' .
						'amount = "' 			. ((isset($_POST['amount'])) 			? $_POST['amount'] 				: '') . '", ' .
						'currency = "' 			. ((isset($_POST['currency'])) 			? $_POST['currency'] 			: '') . '", ' .
						'time = "' 				. ((isset($_POST['time'])) 				? $_POST['time'] 				: '') . '", ' .
						'md5check = "' 			. ((isset($_POST['md5check'])) 			? $_POST['md5check'] 			: '') . '", ' .
						'cardtype = "' 			. ((isset($_POST['cardtype'])) 			? $_POST['cardtype'] 			: '') . '", ' .
						'cardnumber = "' 		. ((isset($_POST['cardnumber'])) 		? $_POST['cardnumber'] 			: '') . '", ' .
						'splitpayment = "' 		. ((isset($_POST['splitpayment'])) 		? $_POST['splitpayment'] 		: '') . '", ' .
						'fraudprobability = "' 	. ((isset($_POST['fraudprobability'])) 	? $_POST['fraudprobability'] 	: '') . '", ' .
						'fraudremarks = "' 		. ((isset($_POST['fraudremarks'])) 		? $_POST['fraudremarks'] 		: '') . '", ' .
						'fraudreport = "' 		. ((isset($_POST['fraudreport'])) 		? $_POST['fraudreport'] 		: '') . '", ' .
						'fee = "' 				. ((isset($_POST['fee'])) 				? $_POST['fee'] 				: '') . '", ' .
						'capturedAmount = "0", '.
						'refundedAmount = "0"  '.
						'where ordernum = "' 	. $_POST['ordernumber'] . '"';

				
				$write = $resource->getConnection('core_write');						
				$write->query($query);
				
				if (((int)$payment->getConfigData('sendmailorderconfirmation')) == 1) {
					$order->sendNewOrderEmail();
				}
				
				
				Mage::helper('quickpaypayment')->createTransaction($order,$_POST['transaction'],Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
				
			}
			else
			{
				$msg = "Der er fejl ved et betalings forsoeg:<br/>";
				$msg .= "Info: <br/>";
				$msg .= "pbsstat: " 		. ((isset($_POST['pbsstat'])) 		? $_POST['pbsstat'] 		: '') . "<br/>";
				$msg .= "qpstat: " 			. ((isset($_POST['qpstat'])) 		? $_POST['qpstat'] 			: '') . "<br/>";
				$msg .= "qpmsg: " 			. ((isset($_POST['qpstatmsg'])) 	? $_POST['qpstatmsg'] 		: '') . "<br/>";
				$msg .= "chstat: " 			. ((isset($_POST['chstat'])) 		? $_POST['chstat'] 			: '') . "<br/>";
				$msg .= "chstatmsg: " 		. ((isset($_POST['chstatmsg'])) 	? $_POST['chstatmsg'] 		: '') . "<br/>";
				$msg .= "merchantemail: " 	. ((isset($_POST['merchantemail'])) ? $_POST['merchantemail'] 	: '') . "<br/>";
				$msg .= "merchant: " 		. ((isset($_POST['merchant'])) 		? $_POST['merchant'] 		: '') . "<br/>";
				$msg .= "amount: " 			. ((isset($_POST['amount'])) 		? $_POST['amount'] 			: '') . "<br/>";
				$msg .= "currency: " 		. ((isset($_POST['currency'])) 		? $_POST['currency'] 		: '') . "<br/>";
				$msg .= "cardtype: " 		. ((isset($_POST['cardtype'])) 		? $_POST['cardtype'] 		: '') . "<br/>";

				//Mage::log($msg);
				$order->addStatusToHistory($order->getStatus(),$msg);
				$order->save();
			}
			
			// Remove items from stock if either not yet removed or only if stock handling is enabled
			if ($_POST['qpstat'] == "000" && $_POST['state'] == '1') {
				// Remove items from stock as the payment now has been made
				if((int)Mage::getStoreConfig('cataloginventory/item_options/manage_stock') == 1) 
				{
					$this->removeFromStock($order->getIncrementId());						
				}        	
			}	
		}
		else 
		{ 
			header("Error: MD5 check failed",true,500);
			exit('md5 mismatch');
		}
        
		// Callback from Quickpay - just respond ok
		echo "OK";
		exit();
    }
}
