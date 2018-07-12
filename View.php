<?php
class Customizations_Rma_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View{
	public function __construct(){
		parent::__construct();

		$order = $this->getOrder();

		if ($this->_isAllowedAction('ship') && $order->canShip()){
			$this->addButton('rma_send', array(
				'label'     => 'Send RMA',
				'onclick'   => 'confirmSetLocation(\'Are you sure you want to send RMA email to customer?\', \'' . 
									$this->getRmaSendUrl() . '\')'
			));

			$this->addButton('rma_receive', array(
				'label'     => 'Receive RMA',
				'onclick'   => 'confirmSetLocation(\'Are you sure you want to send RMA email to customer?\', \'' . 
									$this->getRmaReceiveUrl() . '\')'
			));
		}
	}

	public function getRmaSendUrl(){
		return parent::getUrl('rma/index/index', array('action' => 'send', 'order_id' => $this->getOrderId()));
	}

	public function getRmaReceiveUrl(){
		return parent::getUrl('rma/index/index', array('action' => 'receive', 'order_id' => $this->getOrderId()));
	}

}
?>
