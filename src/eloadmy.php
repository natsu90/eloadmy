<?php

use Goutte\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;

class EloadMy {

	protected $client;

	protected $crawler;

	protected $form;

	public function __construct($param = array())
	{
		if($param) {

			$isUrlValid = $this->setUrl($param['url']);

			if(!$isUrlValid)
				throw new Exception("Invalid Eload URL");
				
			$this->auth($param['mobileno'], $param['password'], $param['agentusername']);
		}
	}

	public function logout()
	{
		$link = $this->crawler->selectLink('Logout')->link();

		$this->client->click($link);
	}

	public function setUrl($url)
	{
		$this->client = new Client(array('UserAgent' => 'EloadMy Library Wrapper'));

		$url = parse_url($url, PHP_URL_SCHEME) === null ? 'http://' . $url : $url;
		$this->crawler = $this->client->request('GET', $url);

		$iframe = $this->crawler->filter('frame');
		if(count($iframe))
			$this->crawler = $this->client->request('GET', $iframe->attr('src'));

		$loginButton = $this->crawler->selectButton('Login');
		if(count($loginButton)) {

			$this->form = $loginButton->form();

			if($this->form->has('ctl00$ContentPlaceHolder1$txtMISISN') && $this->form->has('ctl00$ContentPlaceHolder1$txtPassword'))
				return true;
		}

		return false;
	}
 
	public function auth($mobileNo, $password, $agentUserName = false)
	{
		if($agentUserName)
			$this->client->getCookieJar()->set(new Cookie('AgentUserName', $agentUserName));

		$this->crawler = $this->client->submit($this->form, array(

			'ctl00$ContentPlaceHolder1$txtMISISN' => $mobileNo,

			'ctl00$ContentPlaceHolder1$txtPassword' => $password,

		));

		$loginError = $this->crawler->filter('span[id$=ContentPlaceHolder1_lblError]');
		if(count($loginError) && $loginError->text())
			return false;

		return true;
	}

	public function setTac($tacNumber)
	{
		$tacButton = $this->crawler->selectButton('Continue');
		if(count($tacButton)) {

			$this->form = $tacButton->form();

			$this->crawler = $this->client->submit($this->form, array(

					'ctl00$ContentPlaceHolder1$TextBox1' => $tacNumber,
				));

			$tacError = $this->crawler->filter('span#ContentPlaceHolder1_lblMsg');

			if(count($tacError))
				return false;
		}

		$cookie = $this->client->getCookieJar()->get('AgentUserName');

		return $cookie->getValue();
	}

	public function getInfo()
	{
		$link = $this->crawler->selectLink('AgentInfo')->link();

		$this->crawler = $this->client->click($link);

		$infoCrawler = $this->crawler->filter('table[id$=ContentPlaceHolder1_GridView1] > tr')->eq(1)->filter('td');

		return array(

				'agentID' => $infoCrawler->eq(0)->text(),
				'name' => $infoCrawler->eq(1)->text(),
				'status' => $infoCrawler->eq(3)->text(),
				'hpNo' => $infoCrawler->eq(4)->text(),
				'createdDate' => $infoCrawler->eq(5)->text(),
				'balance' => trim($this->crawler->filter('table[id$=ContentPlaceHolder1_GridView4] > tr')->eq(1)->text())
		);

	}

	public function getCreditBalance()
	{
		$info = $this->getInfo();

		return $info['balance'];
	}

	public function isCreditBalanceSufficient($amount)
	{
		return $this->getCreditBalance() > $amount;
	}

	public function reload($reloadProduct, $amount, $mobileNo)
	{		
		if(!$this->isCreditBalanceSufficient($amount))
			throw new \Exception("Error: Insufficient Credit Balance", 1);		

		if(!$this->isReloadAmountValid($reloadProduct, $amount))
			throw new \Exception("Error: Invalid Reload Amount of ".$amount, 1);	
			
		if(is_string($mobileNo)) {

			$this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->select($amount);

			$this->form->offsetSet('ctl00$ContentPlaceHolder1$txtReloadMSISDN', $mobileNo);

			$this->crawler = $this->client->submit($this->form);

			$confirmReload = $this->crawler->selectButton('Confirm');

			if(!count($confirmReload))
				throw new \Exception("Error: ".$this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text(), 1);

			$this->form = $confirmReload->form();

			$this->crawler = $this->client->submit($this->form);

			return $this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text();

		}

		return false;

	}

	public function getAvailableReloadProducts()
	{

		$reloadLink = $this->crawler->selectLink('Reload');

		if(!count($reloadLink))
			throw new \Exception("Error: Reload link not found." .$this->crawler->html(), 1);
			
		$this->crawler = $this->client->click($reloadLink->link());

		$submitButton = $this->crawler->selectButton('Submit');

		if(!count($submitButton))
			throw new \Exception("Error: Submit reload product button not found." .$this->crawler->html(), 1);
			
		$this->form = $submitButton->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLTelco']->availableOptionValues();

	}

	public function isReloadProductValid($reloadProduct)
	{
		return in_array(strtoupper($reloadProduct), $this->getAvailableReloadProducts());
	}

	public function getAvailableReloadAmount($reloadProduct)
	{
		if(!$this->isReloadProductValid($reloadProduct))
			throw new \Exception("Error: Invalid Reload Product, ".$reloadProduct, 1);

		$this->form['ctl00$ContentPlaceHolder1$DDLTelco']->select(strtoupper($reloadProduct));

		$this->crawler = $this->client->submit($this->form);

		$submitButton = $this->crawler->selectButton('Submit');

		if(!count($submitButton))
			throw new \Exception("Error: Submit reload button not found.", 1);
			
		$this->form = $submitButton->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->availableOptionValues(); 

	}

	public function isReloadAmountValid($reloadProduct, $amount)
	{
		return in_array($amount, $this->getAvailableReloadAmount($reloadProduct));
	}

	public function getStatus($mobileNo = null, $date = null)
	{
		$records = array();

		$link = $this->crawler->selectLink('Search')->link();

		$crawler = $this->client->click($link);

		$form = $crawler->selectButton('Search')->form();

		if($mobileNo)
			$form->offsetSet('ctl00$ContentPlaceHolder1$txtReloadMSISDN', $mobileNo);

		$form->offsetSet('ctl00$ContentPlaceHolder1$txtDate', '');
		if($date)
			$form->offsetSet('ctl00$ContentPlaceHolder1$txtDate', date('Ymd', strtotime($date)));

		$crawler = $this->client->submit($form);

		$record_rows = $crawler->filter('table[id$=ContentPlaceHolder1_GridView1] > tr');

		if(count($record_rows))
			foreach ($record_rows as $key => $value) 
			{
				$crawler = new Crawler($value);

				$data = $crawler->filter('td');

				if(count($data)) {
					$records[] = array(

							'reloadNumber' => $data->eq(0)->text(),

							'amount' => $data->eq(1)->text(),

							'status' => $data->eq(2)->text(),

							'DN' => trim($data->eq(3)->text()),

							'lastUpdatedTimeStamp' => trim($data->eq(4)->text()),

							'telco' => $data->eq(5)->text(),

							'agent' => $data->eq(6)->text(),

							'messageIn' => $data->eq(7)->text(),

							'messageTimeStamp' => $data->eq(8)->text(),

							'code' => trim($data->eq(9)->text()),
						);
				}
			}

		return $records;
	}

	public function getTxHistory($from = null, $to = null)
	{
		$records = array();

		$link = $this->crawler->selectLink('TxHistory')->link();

		$crawler = $this->client->click($link);

		$form = $crawler->selectButton('Search')->form();

		if($from)
			$form['ctl00$ContentPlaceHolder1$txtDateFrom']->setValue(date('Ymd', strtotime($from)));

		if($to)
			$form['ctl00$ContentPlaceHolder1$txtDateTo']->setValue(date('Ymd', strtotime($to)));

		$crawler = $this->client->submit($form);

		$record_rows = $crawler->filter('table[id$=ContentPlaceHolder1_GridView2] > tr');

		if(count($record_rows))
			foreach ($record_rows as $key => $value) 
			{
				$crawler = new Crawler($value);

				$data = $crawler->filter('td');

				if(count($data)) {
					$records[] = array(

							'description' => $data->eq(1)->text(),

							'amount' => $data->eq(2)->text(),

							'balance' => $data->eq(3)->text(),

							'createdTimeStamp' => $data->eq(4)->text(),
						);
				}
			}

		return $records;
	}

	public function payBill($paybillProduct, $amount, $contactNumber, $accountNumber = '')
	{	
		if(!$this->isCreditBalanceSufficient($amount))
			throw new \Exception("Error: Insufficient Credit Balance", 1);		

		if(!$this->isPaybillAmountValid($paybillProduct, $amount))
			throw new \Exception("Error: Invalid Paybill Amount", 1);

		if(is_string($contactNumber) && is_string($accountNumber)) {

			$this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->select($amount);

			$this->form['ctl00$ContentPlaceHolder1$txtReloadMSISDN']->setValue($contactNumber);

			$this->form['ctl00$ContentPlaceHolder1$txtName1']->setValue($accountNumber);

			$this->crawler = $this->client->submit($this->form);

			$confirmPaybill = $this->crawler->selectButton('Confirm');

			if(!count($confirmPaybill))
				throw new \Exception("Error: ".$this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text(), 1);

			$this->form = $confirmPaybill->form();

			$this->crawler = $this->client->submit($this->form);

			return $this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text();
		}

		return false;
			
	}

	public function getAvailablePaybillProducts()
	{
		$link = $this->crawler->selectLink('Paybill')->link();

		$this->crawler = $this->client->click($link);

		$this->form = $this->crawler->selectButton('Submit')->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLTelco']->availableOptionValues();
	}

	public function isPaybillProductValid($paybillProduct)
	{
		return in_array(strtoupper($paybillProduct), $this->getAvailablePaybillProducts());
	}

	public function getAvailablePaybillAmount($paybillProduct)
	{
		if(!$this->isPaybillProductValid($paybillProduct))
			throw new \Exception("Error: Invalid Paybill Product", 1);

		$this->form['ctl00$ContentPlaceHolder1$DDLTelco']->select(strtoupper($paybillProduct));

		$this->crawler = $this->client->submit($this->form);

		$this->form = $this->crawler->selectButton('Submit')->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->availableOptionValues(); 
			
	}

	public function isPaybillAmountValid($paybillProduct, $amount)
	{
		return in_array($amount, $this->getAvailablePaybillAmount($paybillProduct));
	}

	public function reloadPin($reloadPinProduct, $amount, $mobileNo)
	{
		if(!$this->isCreditBalanceSufficient($amount))
			throw new \Exception("Error: Insufficient Credit Balance", 1);	

		if(!$this->isReloadPinAmountValid($reloadPinProduct, $amount))
			throw new \Exception("Error: Invalid Reload PIN Amount", 1);

		if(is_string($mobileNo)) {

			$this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->select($amount);

			$this->form['ctl00$ContentPlaceHolder1$txtReloadMSISDN']->setValue($mobileNo);

			$this->crawler = $this->client->submit($this->form);

			$confirmReloadPin = $this->crawler->selectButton('Confirm');

			if(!count($confirmReloadPin))
				throw new \Exception("Error: ".$this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text(), 1);

			$this->form = $confirmReloadPin->form();

			$this->crawler = $this->client->submit($this->form);

			return $this->crawler->filter('span[id$=ContentPlaceHolder1_lblErrorMsg]')->text();
		}

		return false;
	}

	public function getAvailableReloadPinProducts()
	{
		$link = $this->crawler->selectLink('Reload PIN')->link();

		$this->crawler = $this->client->click($link);

		$this->form = $this->crawler->selectButton('Submit')->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLTelco']->availableOptionValues();
	}

	public function isReloadPinProductValid($reloadPinProduct)
	{
		return in_array(strtoupper($reloadPinProduct), $this->getAvailableReloadPinProducts());
	}

	public function getAvailableReloadPinAmount($reloadPinProduct)
	{
		if(!$this->isReloadPinProductValid($reloadPinProduct))
			throw new \Exception("Error: Invalid Reload PIN Product", 1);

		$this->form['ctl00$ContentPlaceHolder1$DDLTelco']->select(strtoupper($reloadPinProduct));

		$this->crawler = $this->client->submit($this->form);

		$this->form = $this->crawler->selectButton('Submit')->form();

		return $this->form['ctl00$ContentPlaceHolder1$DDLReloadAmount']->availableOptionValues();
			
	}

	public function isReloadPinAmountValid($reloadPinProduct, $amount)
	{
		return in_array($amount, $this->getAvailableReloadPinAmount($reloadPinProduct));
	}
}