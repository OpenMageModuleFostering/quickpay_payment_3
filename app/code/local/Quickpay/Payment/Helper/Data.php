<?php
class Quickpay_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{

	public function capture($payment,$amount,$finalize = false)
    {
    	//$invoice = $observer->getInvoice();
		$session = Mage::getSingleton('adminhtml/session');	
		
		if($payment->getInfoInstance())
		{
			$order = $payment->getInfoInstance()->getOrder();
		}
		else
		{
			$order = $payment->getOrder();
		}
		
		$orderid = explode("-",$order->getIncrementId());
		
		$resource = Mage::getSingleton('core/resource');
		$connection = $resource->getConnection('core_read');
		$table = $resource->getTableName('quickpaypayment_order_status');
		$qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);
		$qpOrderStatus = $qpOrderStatus[0];
			
		$capturedAmount = (isset($qpOrderStatus['capturedAmount']) ? $qpOrderStatus['capturedAmount'] : 0 );
	
		if((int)($amount*100) <= ((int)$qpOrderStatus['amount']-(int)$capturedAmount) )
		{
			$qpmd5 = Mage::getStoreConfig('payment/quickpaypayment_payment/md5secret');
			$merchant = Mage::getStoreConfig('payment/quickpaypayment_payment/merchantnumber');
			
			$msg = Array(
				'protocol' => 3,
				'msgtype' => 'capture',
				'merchant' => $merchant,
				'amount' => $amount*100,
				'transaction' => $qpOrderStatus['transaction'],
				 );	 
			
			// DELHÆVNING
			if($order->getTotalDue() == $amount || $qpOrderStatus['cardtype'] != 'dankort' || $finalize)
			{
				$msg['finalize'] = 1;
			}
			else
			{
				$msg['finalize'] = 0;
			}
			
			$newCapturedAmount = $capturedAmount + ($amount * 100);
				 
			$msg['md5check'] = md5($msg['protocol'] . $msg['msgtype'] . $msg['merchant'] . $msg['amount'] . $msg['finalize'] . $msg['transaction'] . $qpmd5);
				
			$response = $this->transmit($msg);
			
			if($response === "")
			{
				throw new Exception(Mage::helper('quickpaypayment')->__('Tomt svar modtaget fra Quickpay API'));
			} 
			elseif($response === false)
			{
				throw new Exception(Mage::helper('quickpaypayment')->__('Ingen svar modtaget fra Quickpay API'));
			}
			
			$response = $this->responseToArray($response);
			
			if($response['qpstat'] == "000")
			{
				$session->addSuccess(Mage::helper('quickpaypayment')->__('Betalingen er hævet online.'));
				$write = $resource->getConnection('core_write');
				$write->query("UPDATE $table SET " .
								'status = "'		. ((isset($response['state'])) 			? $response['state'] 		: '') . '", ' .
								'time = "' 			. ((isset($response['time'])) 			? $response['time'] 		: '') . '", ' .
								'qpstat = "'		. ((isset($response['qpstat'])) 		? $response['qpstat'] 		: '') . '", ' .
								'qpstatmsg = "'		. ((isset($response['qpstatmsg'])) 		? $response['qpstatmsg'] 	: '') . '", ' .
								'chstat = "'		. ((isset($response['chstat'])) 		? $response['chstat'] 		: '') . '", ' .
								'chstatmsg = "'		. ((isset($response['chstatmsg'])) 		? $response['chstatmsg'] 	: '') . '", ' .
								'splitpayment = "' 	. ((isset($response['splitpayment']))	? $response['splitpayment'] : '') . '" ,' .
								'md5check = "' 		. ((isset($response['md5check'])) 		? $response['md5check'] 	: '') . '", ' .
								'capturedAmount = ' . $newCapturedAmount . ' ' .
								'WHERE ordernum=' 	. $orderid[0]);
								
				$this->createTransaction($order,$msg['transaction'],Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
			}
			else
			{
				throw new Exception("Quickpay Response: " . $response['qpstatmsg']);
			}	
		}
		else
		{
			throw new Exception(Mage::helper('quickpaypayment')->__('Der forsøges at hæve et højere beløb en tilladt'));
		}    
    }
    
	public function refund($orderid,$refundtotal)
    {
    	$order = Mage::getModel('sales/order')->load($orderid);
		$orderid = explode("-",$order->getIncrementId());
		
		$session = Mage::getSingleton('adminhtml/session');
		$resource = Mage::getSingleton('core/resource');
		$connection = $resource->getConnection('core_read');
		$table = $resource->getTableName('quickpaypayment_order_status');
		$qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);
		$qpOrderStatus = $qpOrderStatus[0];
		
		$qpmd5 = Mage::getStoreConfig('payment/quickpaypayment_payment/md5secret');
		$merchant = Mage::getStoreConfig('payment/quickpaypayment_payment/merchantnumber');
		
		if($refundtotal < 0)
		{
			$refundtotal = $refundtotal*-1;
		}
		
		if(($refundtotal*100) <= $qpOrderStatus['capturedAmount'])
		{
			$msg = Array(
				'protocol' => 3,
				'msgtype' => 'refund',
				'merchant' => $merchant,
				'amount' => $refundtotal*100,
				'transaction' => $qpOrderStatus['transaction'],
				 );
			$msg['md5check'] = md5($msg['protocol'] . $msg['msgtype'] . $msg['merchant'] . $msg['amount'] . $msg['transaction'] . $qpmd5);
			
					
			$response = Mage::helper('quickpaypayment')->transmit($msg);


			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////
			/////////////// 		GRIB DEN RIGTIGE EXCEPTION			////////////
			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////
			////////////////////////////////////////////////////////////////////////

			// NO RESPONCE - API HUSK TILFØJ IP
			if($response === "")
			{
				throw new Exception(Mage::helper('quickpaypayment')->__('Tomt svar modtaget fra Quickpay API'));
			}
			if($response === false)
			{
				throw new Exception(Mage::helper('quickpaypayment')->__('Ingen svar modtaget fra Quickpay API'));
			}
			
			
			$response = Mage::helper('quickpaypayment')->responseToArray($response);

			if($response['qpstat'] == "000")
			{
				$session->addSuccess(Mage::helper('quickpaypayment')->__('Kreditnota refunderet online'));
				$write = Mage::getSingleton('core/resource')->getConnection('core_write');
				$write->query("UPDATE $table SET ".
									'refundedAmount = ' . ($refundtotal*100) . ', ' .
									'status = "'.((isset($response['state'])) ? $response['state'] : '0').'", ' .
									'time = "' . ((isset($response['time'])) ? $response['time'] : '0') . '", '.
									'qpstat = "'.((isset($response['qpstat'])) ? $response['qpstat'] : '0').'", ' .
									'qpstatmsg = "'.((isset($response['qpstatmsg'])) ? $response['qpstatmsg'] : '0').'", ' .
									'chstat = "'.((isset($response['chstat'])) ? $response['chstat'] : '0').'", ' .
									'chstatmsg = "'.((isset($response['chstatmsg'])) ? $response['chstatmsg'] : '0').'" ' .
									'WHERE ordernum=' . $orderid[0]);
									
				$this->createTransaction($order,$msg['transaction'],Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
				
									
			}
			else
			{	
				throw new Exception($response['qpstatmsg']);
			}	
		}
		else
		{
			throw new Exception(Mage::helper('quickpaypayment')->__('Max beløb der kan refunderes: %s',$qpOrderStatus['capturedAmount']));
		}

    	$order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Kreditnota refunderede % online', number_format($refundtotal,2,",","")), false);
		$order->save();
    }
    
    public function cancel($orderid)
    {
    	$order = Mage::getModel('sales/order')->load($orderid);
		$orderid = explode("-",$order->getIncrementId());
		
		$session = Mage::getSingleton('adminhtml/session');
		$resource = Mage::getSingleton('core/resource');
		$connection = $resource->getConnection('core_read');
		$table = $resource->getTableName('quickpaypayment_order_status');
		$qpOrderStatus = $connection->fetchAll("SELECT * FROM $table WHERE ordernum=" . $orderid[0]);
		$qpOrderStatus = $qpOrderStatus[0];
		
		$qpmd5 = Mage::getStoreConfig('payment/quickpaypayment_payment/md5secret');
		$merchant = Mage::getStoreConfig('payment/quickpaypayment_payment/merchantnumber');
		
		$msg = Array(
			'protocol' => 4,
			'msgtype' => 'cancel',
			'merchant' => $merchant,
			'transaction' => $qpOrderStatus['transaction'],
			 );
		$msg['md5check'] = md5($msg['protocol'] . $msg['msgtype'] . $msg['merchant'] . $msg['transaction'] . $qpmd5);

		$response = Mage::helper('quickpaypayment')->transmit($msg);

		// NO RESPONCE - API HUSK TILFØJ IP
		if($response === "")
		{
			throw new Exception(Mage::helper('quickpaypayment')->__('Tomt svar modtaget fra Quickpay API'));
		}
		if($response === false)
		{
			throw new Exception(Mage::helper('quickpaypayment')->__('Ingen svar modtaget fra Quickpay API'));
		}
		
		$response = Mage::helper('quickpaypayment')->responseToArray($response);
		
		if($response['qpstat'] == "000")
		{
			$session->addSuccess(Mage::helper('quickpaypayment')->__('Betalingen blev annulleret online'));
			$write = Mage::getSingleton('core/resource')->getConnection('core_write');

			$write->query("UPDATE $table SET ".
								'status = "'.((isset($response['state'])) ? $response['state'] : '0').'", ' .
								'time = "' . ((isset($response['time'])) ? $response['time'] : '0') . '", '.
								'qpstat = "'.((isset($response['qpstat'])) ? $response['qpstat'] : '0').'", ' .
								'qpstatmsg = "'.((isset($response['qpstatmsg'])) ? $response['qpstatmsg'] : '0').'", ' .
								'chstat = "'.((isset($response['chstat'])) ? $response['chstat'] : '0').'", ' .
								'chstatmsg = "'.((isset($response['chstatmsg'])) ? $response['chstatmsg'] : '0').'" ' .
								'WHERE ordernum=' . $orderid[0]);				
		}
		else
		{	
			throw new Exception($response['qpstatmsg']);
		}	

    	$order->addStatusToHistory($order->getStatus(), Mage::helper('quickpaypayment')->__('Betalingen blev annulleret online'), false);
		$order->save();
    }
    

	public function createTransaction($order,$transactionId,$type)
	{	
		$transaction = Mage::getModel('sales/order_payment_transaction');
		$transaction->setOrderPaymentObject($order->getPayment());

		if(!$transaction = $transaction->loadByTxnId($transactionId))
		{			
			$transaction = Mage::getModel('sales/order_payment_transaction');
			$transaction->setOrderPaymentObject($order->getPayment());
			$transaction->setOrder($order);
		}
		if($type == Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH)
		{
			$transaction->setIsClosed(false);
		}
		else
		{
			$transaction->setIsClosed(true);
		}

		$transaction->setTxnId($transactionId);		
		$transaction->setTxnType($type);
		$transaction->save();
		
		return $transaction;
	}


    public function transmit(Array $msg)
	{
		$context = stream_context_create(
            array(
                'http' => array(
                    'method' => 'POST',
                    'content' => http_build_query($msg, false, '&'),
                ),
            )
        );

        if (!$fp = @fopen('https://secure.quickpay.dk/api', 'r', false, $context)) {
            throw new Exception('Could not connect to gateway');
        }
		
        if (($response = @stream_get_contents($fp)) === false) {
            throw new Exception('Could not read data from gateway');
        }
        	
		return $response;
	}

    
    /**
     * Converts QuickPay XML response to an array
     *
     * @param string $response
     * @return array
     */
    public function responseToArray($response) {
        // Load XML in response into DOM
        $result = array();
        $dom = new DOMDocument();
        $dom->loadXML($response);
        // Find elements en response and put them in an associative array
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('/response/*');
        foreach ($elements as $cn) {
            // If the element has (real) children - this is the case for status->history and chstatus->entry
            if ($cn->childNodes->length > 1) {
                foreach ($cn->childNodes as $hn) {
                        $result[$cn->nodeName][intval($i)][$hn->nodeName] = $hn->nodeValue;
                }
                $i++;
            } else {
                $result[$cn->nodeName] = $cn->nodeValue;
            }
        }

        return $result;
    }
    
    
}
