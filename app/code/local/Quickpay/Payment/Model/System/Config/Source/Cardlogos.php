<?php
class Quickpay_Payment_Model_System_Config_Source_Cardlogos
{

    public function toOptionArray()
    {
        return array(
            array('value'=>'dankort', 				'label'=>Mage::helper('quickpaypayment')->__('Dankort')),
            array('value'=>'edankort', 				'label'=>Mage::helper('quickpaypayment')->__('eDankort')),
            array('value'=>'danskenetbetaling', 	'label'=>Mage::helper('quickpaypayment')->__('Danske Netbetaling')),
            array('value'=>'nordea', 				'label'=>Mage::helper('quickpaypayment')->__('Nordea e-betaling')),
            array('value'=>'ewire', 				'label'=>Mage::helper('quickpaypayment')->__('EWIRE')),
            array('value'=>'forbrugsforeningen', 	'label'=>Mage::helper('quickpaypayment')->__('Forbrugsforeningen')),
            array('value'=>'visa', 					'label'=>Mage::helper('quickpaypayment')->__('VISA')),
            array('value'=>'visaelectron', 			'label'=>Mage::helper('quickpaypayment')->__('VISA Electron')),
            array('value'=>'mastercard', 			'label'=>Mage::helper('quickpaypayment')->__('MasterCard')),
            array('value'=>'maestro', 				'label'=>Mage::helper('quickpaypayment')->__('Maestro')),
            array('value'=>'jcb', 					'label'=>Mage::helper('quickpaypayment')->__('JCB')),
            array('value'=>'diners', 				'label'=>Mage::helper('quickpaypayment')->__('Diners Club')),
            array('value'=>'amex', 					'label'=>Mage::helper('quickpaypayment')->__('AMEX')),
        );
    }

}
