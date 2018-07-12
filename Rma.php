<?php
/**
 * RMA model
 *
 * @method bool initSend(int $order_id)
 * @method bool initReceive(int $order_id)
 * @method sendSendEmail(int $order_type)
 * @method sendReceiveEmail()
 * @method string parsePdf()
 * @method int characterizeOrder(Mage_Sales_Model_Order $order)
 * @method int verifyCat(Mage_Sales_Model_Order_Item $item)
 * @method string getWrappedText(string $string, Zend_Pdf_Font $font, int $font_size, int $max_width)
 * @method int widthForStringUsingFontSize(string $string, Zend_Pdf_Font $font, int $font_size)
 *
 * @category    Customizations
 * @package     Customizations_Rma
 * @author      n/a
 */

class Customizations_Rma_Model_Rma extends Mage_Core_Model_Abstract{

	/**
	* Order types
	*/
	const SERVICE_ORDER = 1;
	const RETURNS_ORDER = 2;

	/**
	* Order ID's
	*
	* @var int
	*/
	private $order_id;

	/**
	* Current store
	*
	* @var Mage_Core_Model_Store
	*/
	private $store;

	/**
	* Current order
	*
	* @var Mage_Sales_Model_Order
	*/
	private $order;

	/**
	* Array of US states and abbreviations
	*
	* @var array
	*/
	private $states = array(
		"Alabama"		=> "AL",	"Alaska"		=> "AK",	"Arizona"		=> "AZ",	"Arkansas"		=> "AR",	"California"	=> "CA",
		"Colorado"		=> "CO",	"Connecticut"	=> "CT",	"Delaware"		=> "DE",	"Florida"		=> "FL",	"Georgia"		=> "GA",
		"Hawaii"		=> "HI",	"Idaho"			=> "ID",	"Illinois"		=> "IL",	"Indiana"		=> "IN",	"Iowa"			=> "IA",
		"Kansas"		=> "KS",	"Kentucky"		=> "KY",	"Louisiana"		=> "LA",	"Maine"			=> "ME",	"Maryland"		=> "MD",
		"Massachusetts"	=> "MA",	"Michigan"		=> "MI",	"Minnesota"		=> "MN",	"Mississippi"	=> "MS",	"Missouri"		=> "MO",
		"Montana"		=> "MT",	"Nebraska"		=> "NE",	"Nevada"		=> "NV",	"New Hampshire"	=> "NH",	"New Jersey"	=> "NJ",
		"New Mexico"	=> "NM",	"New York"		=> "NY",	"North Carolina"=> "NC",	"North Dakota"	=> "ND",	"Ohio"			=> "OH",
		"Oklahoma"		=> "OK",	"Oregon"		=> "OR",	"Pennsylvania"	=> "PA",	"Rhode Island"	=> "RI",	"South Carolina"=> "SC",
		"South Dakota"	=> "SD",	"Tennessee"		=> "TN",	"Texas"			=> "TX",	"Utah"			=> "UT",	"Vermont"		=> "VT",
		"Virginia"		=> "VA",	"Washington"	=> "WA",	"West Virginia"	=> "WV",	"Wisconsin"		=> "WI",	"Wyoming"		=> "WY");

	public function _construct(){
		parent::_construct();

		$this->store = Mage::getModel('core/store')->load(0);
	}

	/**
	* Initialize RMA/sending instructions
	*
	* @param int $order_id
	* @return bool
	*/
	public function initSend($order_id){
		$this->order_id = $order_id;
		$this->order = Mage::getModel('sales/order')->load($this->order_id);

		$order_type = $this->characterizeOrder($this->order);
		if($order_type == self::SERVICE_ORDER){
			if($this->order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING){
				$this->order->addStatusToHistory('waiting');
				$this->order->save();
			}
			$this->sendSendEmail(self::SERVICE_ORDER);
			return true;
		}else if($order_type == self::RETURNS_ORDER){
			$this->sendSendEmail(self::RETURNS_ORDER);
			return true;
		}else{
			return false;
		}
	}

	/**
	* Initialize RMA/receipt acknowledgment
	*
	* @param int $order_id
	* @return bool
	*/
	public function initReceive($order_id){
		$this->order_id = $order_id;
		$this->order = Mage::getModel('sales/order')->load($this->order_id);

		if($this->characterizeOrder($this->order) != false){
			if($this->order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING){
				$this->order->addStatusToHistory('processing');
				$this->order->save();
			}
			$this->sendReceiveEmail();
			return true;
		}else{
			return false;
		}

	}

	/**
	* Send an email with RMA/SMA sending instructions
	*
	* @param int $order_type
	*/
	private function sendSendEmail($order_type){
		if($order_type == self::SERVICE_ORDER){
			$email  = Mage::getModel('core/email_template')->loadDefault('sma_email_template');
			$email->setTemplateSubject('Order #' . $this->order->getIncrementId() .
				' SMA Information - ' . Mage::getStoreConfig('general/store_information/name'));
		}else{
			$email  = Mage::getModel('core/email_template')->loadDefault('rma_email_template');
			$email->setTemplateSubject('Order #' . $this->order->getIncrementId() .
				' RMA Information - ' . Mage::getStoreConfig('general/store_information/name'));
		}

		$email_vars = array();
		$email_vars['store'] = $this->store;
		$email_vars['order'] = $this->order;
		$processedTemplate = $email->getProcessedTemplate($email_vars);

		$email->setSenderName(Mage::getStoreConfig('trans_email/ident_support/name'));
		$email->setSenderEmail(Mage::getStoreConfig('trans_email/ident_support/email'));

		$attachment = $email->getMail()->createAttachment($this->parsePdf(), 'application/pdf');
		$attachment->filename = 'Shipping_Instructions.pdf';

		$email->send($email_vars['order']->getData('customer_email'), $email_vars['order']->getCustomerName(), $email_vars); 
	}

	/**
	* Send an RMA/SMA receipt acknowledgment email
	*/
	private function sendReceiveEmail(){
		$email  = Mage::getModel('core/email_template')->loadDefault('received_email_template');
		$email->setTemplateSubject('Order #' . $this->order->getIncrementId() .
			' SMA Information - ' . Mage::getStoreConfig('general/store_information/name'));

		$email_vars = array();
		$email_vars['store'] = $this->store;
		$email_vars['order'] = $this->order;
		$processedTemplate = $email->getProcessedTemplate($email_vars);

		$email->setSenderName(Mage::getStoreConfig('trans_email/ident_support/name'));
		$email->setSenderEmail(Mage::getStoreConfig('trans_email/ident_support/email'));

		$email->send($email_vars['order']->getData('customer_email'), $email_vars['order']->getCustomerName(), $email_vars); 
	}

	/**
	* Create a PDF document with a customized shipping label and RMA/SMA form
	*
	* @return string
	*/
	private function parsePdf(){
		$arial_font = Zend_Pdf_Font::fontWithPath(Mage::getBaseDir('lib') . '/ArialFont/arial.ttf');

		$pdf = Zend_Pdf::load(Mage::getBaseDir('media') . '/rma/rma_label_form.pdf');

		$pdf->properties['Title'] = 'Shipping Instructions';

		$barcode_options = array(
			'factor' => 3,
			'font' => Mage::getBaseDir('lib') . '/ArialFont/arial.ttf',
			'fontSize' => 6,
			'barHeight' => 15,
			'withChecksum' => true,
			'text' => str_pad($this->order_id, 9, '0', STR_PAD_LEFT)); 
		$renderer_options = array(
			'topOffset' => 308,
			'leftOffset' => 210);
		Zend_Barcode::factory('code128', 'pdf', $barcode_options, $renderer_options)->setResource($pdf, 0)->draw();

		$barcode_options = array(
			'factor' => 5,
			'font' => Mage::getBaseDir('lib') . '/ArialFont/arial.ttf',
			'drawText' => false,
			'barHeight' => 30,
			'withChecksum' => true,
			'text' => str_pad($this->order_id, 9, '0', STR_PAD_LEFT)); 
		$renderer_options = array(
			'topOffset' => 190,
			'leftOffset' => 155);
		Zend_Barcode::factory('code128', 'pdf', $barcode_options, $renderer_options)->setResource($pdf, 1)->draw(); 

		$pdf->pages[0]->setFont($arial_font, 12);
		$pdf->pages[0]->drawText($this->order->getCustomerName(), 117, 675);

		$pdf->pages[0]->setFont($arial_font, 14);
		$pdf->pages[0]->drawText(Mage::getStoreConfig('general/store_information/name') . ", LLC", 210, 570);

		$pdf->pages[0]->drawText('Service Department', 210, 555);

		foreach (explode(PHP_EOL, Mage::getStoreConfig('general/store_information/address')) as $i => $address_line){
			$pdf->pages[0]->drawText(trim($address_line), 210, (540 - (15 * $i)));
		}

		$pdf->pages[1]->setFont($arial_font, 10);
		$pdf->pages[1]->drawText(date('F j, Y'), 117, 677);

		$pdf->pages[1]->setFont($arial_font, 13);
		$pdf->pages[1]->drawText($this->order->getIncrementId(), 128, 664);

		$pdf->pages[1]->setFont($arial_font, 10);
		$pdf->pages[1]->drawText(date('F j, Y', time() + 2592000), 330, 639);

		$pdf->pages[1]->setFont($arial_font, 12);
		$pdf->pages[1]->drawText($this->order->getCustomerName(), 65, 478);

		if($this->order->getShippingAddress() != false){
			$pdf->pages[1]->drawText(str_replace("\n", ", ", $this->order->getShippingAddress()->getData('street')), 65, 442);

			$telephone_digits = array();
			if(preg_match_all("/[0-9]/", trim($this->order->getShippingAddress()->getData('telephone')), $telephone_digits) == 10){
				$pdf->pages[1]->drawText(
					'(' . $telephone_digits[0][0] . $telephone_digits[0][1] . $telephone_digits[0][2] . ') ' . 
					$telephone_digits[0][3] . $telephone_digits[0][4] . $telephone_digits[0][5] . ' - ' . 
					$telephone_digits[0][6] . $telephone_digits[0][7] . $telephone_digits[0][8] . $telephone_digits[0][9], 65, 406);
			}else{
				$pdf->pages[1]->drawText($this->order->getShippingAddress()->getData('telephone'), 65, 406);
			}

			$pdf->pages[1]->drawText($this->order->getShippingAddress()->getData('city'), 326, 442);

			if($this->order->getShippingAddress()->getData('country_id') == 'US'){
				$pdf->pages[1]->drawText($this->states[$this->order->getShippingAddress()->getData('region')], 450, 442);
			}

			$pdf->pages[1]->drawText($this->order->getShippingAddress()->getData('postcode'), 500, 442);

			$pdf->pages[1]->drawText($this->order->getShippingAddress()->getData('email'), 326, 406);
		}

		$line_count = 380;
		$items = $this->order->getAllVisibleItems();
		foreach ($items as $item_id => $item){
			if($this->verifyCat($item) != false){
				$pdf->pages[1]->setFont($arial_font, 11);
				$pdf->pages[1]->drawText($item->getName(), 55, $line_count);
				$line_count -= 12;

				$options = $item->getProductOptions();
				foreach($options['options'] as $option){
					if($option['option_type'] != "file"){
						$line_count -= 2;
						$pdf->pages[1]->setFont($arial_font, 10);
						$pdf->pages[1]->drawText('- ' . $option['label'], 65, $line_count);
						$line_count -= 11;

						$pdf->pages[1]->setFont($arial_font, 10);
						$option['print_value'] = str_replace("\r", "", $option['print_value']);
						$lines = explode("\n", $this->getWrappedText($option['print_value'], $arial_font, 10, 450));
						foreach($lines as $line){
							if($line != ''){
								$pdf->pages[1]->drawText($line, 85, $line_count);
								$line_count -= 11;
							}
						}
					}
				}
				$line_count -= 5;
			}
		}

		return $pdf->render();
	}

	/**
	* Determine the type of order (services order, or a warranty claim/return)
	*
	* @param Mage_Sales_Model_Order $order
	* @return int
	*/
	private function characterizeOrder($order){
		$is_service = false;
		$is_return = false;

		$items = $order->getAllVisibleItems();
		foreach ($items as $item_id => $item){
			if($this->verifyCat($item) == self::SERVICE_ORDER){
				$is_service = true;
			}
			if($this->verifyCat($item) == self::RETURNS_ORDER){
				$is_return = true;
			}
		}

		if($is_return){
			return self::RETURNS_ORDER;
		}else if($is_service){
			return self::SERVICE_ORDER;
		}else{
			return false;
		}
	}

	/**
	* Ensure the item being inserted in the RMA/SMA form is a service, claim, or return
	*
	* @param string $item
	* @return int
	*/
	private function verifyCat($item){
		$cat_ids = Mage::getModel('catalog/product')->load($item->getProductId())->getCategoryIds();
		$in_services = false;
		$in_returns = false;

		foreach($cat_ids as $cat_id){
			$cat = strtolower(Mage::getModel('catalog/category')->load($cat_id)->getName());
			if($cat == "services" || $cat == "misc"){
				$in_services = true;
			}
			if($cat == "returns & claims"){
				$in_returns = true;
			}
		}

		if($in_returns){
			return self::RETURNS_ORDER;
		}else if($in_services){
			return self::SERVICE_ORDER;
		}else{
			return false;
		}
	}

	/**
	* Wrap long strings to fit in the confines of a PDF document
	*
	* @param string $string
	* @param Zend_Pdf_Font $font
	* @param int $font_size
	* @param int $max_width
	* @return string
	*/
	private function getWrappedText($string, $font, $font_size, $max_width){
		$wrapped_text = '';
		$lines = explode("\n", $string);
		foreach($lines as $line) {
			$words = explode(' ', $line);
			$word_count = count($words);
			$i = 0;
			$wrapped_line = '';
			while($i < $word_count){
				if($this->widthForStringUsingFontSize($wrapped_line . ' ' . $words[$i], $font, $font_size) < $max_width){
					if(!empty($wrapped_line)){
						$wrapped_line .= ' ';
					}
					$wrapped_line .= $words[$i];
				}else{
					$wrapped_text .= $wrapped_line . "\n";
					$wrapped_line = $words[$i];
				}
				$i++;
			}
			$wrapped_text .= $wrapped_line . "\n";
		}
		return $wrapped_text;
	}

	/**
	* Determine the maximum width of a string for a given font size
	*
	* @param string $string
	* @param Zend_Pdf_Font $font
	* @param int $font_size
	* @return int
	*/
	private function widthForStringUsingFontSize($string, $font, $font_size){
		$drawing_string = iconv('UTF-8', 'UTF-16BE//IGNORE', $string);
		$characters = array();
		for ($i = 0 ; $i < strlen($drawing_string) ; $i++){
			$characters[] = (ord($drawing_string[$i++]) << 8 ) | ord($drawing_string[$i]);
		}
		$glyphs = $font->glyphNumbersForCharacters($characters);
		$widths = $font->widthsForGlyphs($glyphs);
		$stringWidth = (array_sum($widths) / $font->getUnitsPerEm()) * $font_size;
		return $stringWidth;
	}
}
?>
