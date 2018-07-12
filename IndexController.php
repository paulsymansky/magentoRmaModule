<?php
/**
 * RMA index controller
 *
 * @method indexAction()
 *
 * @category    Customizations
 * @package     Customizations_Rma
 * @author      n/a
 */

class Customizations_Rma_IndexController extends Mage_Adminhtml_Controller_Action{

	/**
	* Routes GET requests from button clicks to Customizations_Rma_Model_Rma class
	*/
	public function indexAction(){
		$model = Mage::getModel('rma/rma');

		$order_id = $this->getRequest()->getParam('order_id');
		if($this->getRequest()->getParam('action') == 'send'){
			if($model->initSend($order_id)){
				Mage::getSingleton('core/session')->addSuccess('The RMA email has been sent.');
				$this->_redirect('adminhtml/sales_order/view/order_id/', array('order_id' => $order_id));
			}else{
				Mage::getSingleton('core/session')->addError('The RMA email could not be sent: no services or returns in order.');
				$this->_redirect('adminhtml/sales_order/view/', array('order_id' => $order_id));
			}
		}else if($this->getRequest()->getParam('action') == 'receive'){
			if($model->initReceive($order_id)){
				Mage::getSingleton('core/session')->addSuccess('The RMA email has been sent.');
				$this->_redirect('adminhtml/sales_order/view/order_id/', array('order_id' => $order_id));
			}else{
				Mage::getSingleton('core/session')->addError('The RMA email could not be sent: no services or returns in order.');
				$this->_redirect('adminhtml/sales_order/view/order_id/', array('order_id' => $order_id));
			}
		}
	}
}
?>
