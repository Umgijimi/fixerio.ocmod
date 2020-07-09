<?php
class ControllerExtensionModuleFixerioForexRates extends Controller {
	
	private $version = '0.0.1';
    private $error = array();
  
    public function index() {
		$this->load->language('extension/module/fixerio_forex_rates');
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			
			//Update update date 
			$this->request->post['module_fixerio_forex_rates_last_update'] = date('Y-m-d H:i:s');
			
			 $this->model_setting_setting->editSetting('module_fixerio_forex_rates', $this->request->post);
			
			//$this->cache->delete('fixerio_forex_rates');
			 
			$this->session->data['success'] = $this->language->get('text_success');
			  
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}
		
		if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
		
		$data = array();
		
		$data['heading_title'] = $this->language->get('heading_title') . ' ' . $this->version;

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/fixerio_forex_rates', 'user_token=' . $this->session->data['user_token'], true)
		);
		
	

		if (isset($this->request->post['module_fixerio_forex_rates_access_key'])) {
			$data['module_fixerio_forex_rates_access_key'] = $this->request->post['module_fixerio_forex_rates_access_key'];
		} elseif ($this->config->get('module_fixerio_forex_rates_access_key')) {
			$data['module_fixerio_forex_rates_access_key'] = $this->config->get('module_fixerio_forex_rates_access_key');
		} else {
			$data['module_fixerio_forex_rates_access_key'] = '';
		}
		
		if (isset($this->request->post['module_fixerio_forex_rates_status'])) {
			$data['module_fixerio_forex_rates_status'] = $this->request->post['module_fixerio_forex_rates_status'];
		} else {
			$data['module_fixerio_forex_rates_status'] = $this->config->get('module_fixerio_forex_rates_status');
		} 
		
		if($this->config->get('module_fixerio_forex_rates_last_update')){
			$data['module_fixerio_forex_rates_last_update'] = $this->config->get('module_fixerio_forex_rates_last_update');
		}else{
			$data['module_fixerio_forex_rates_last_update'] = ''; 
		}
		
		$data['action']['cancel'] = $this->url->link('marketplace/extension', 'user_token='.$this->session->data['user_token'].'&type=module');
		$data['action']['save'] = "";

		$data['error'] = $this->error;	
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$this->response->setOutput($this->load->view('extension/module/fixerio_forex_rates', $data));
	}

    public function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/fixerio_forex_rates')) {
			$this->error['permission'] = true;
			return false;
		}
		
		if (!utf8_strlen($this->request->post['module_fixerio_forex_rates_access_key'])) {
			$this->error['fixerio_forex_rates_access_key'] = true;
		}
		
		return empty($this->error);

	}
 
    public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('fixerio_forex_rates');
		
		//catalog/view/common/header/after
        //$this->model_setting_event->addEvent('fixerio_forex_rates', 'catalog/controller/*/before', 'extension/module/fixerio_forex_rates/update_rates');
		$this->model_setting_event->addEvent('fixerio_forex_rates', 'admin/controller/common/header/after', 'extension/module/fixerio_forex_rates/update_rates');
		$this->model_setting_event->addEvent('fixerio_forex_rates', 'catalog/controller/common/header/after', 'extension/module/fixerio_forex_rates/update_rates');
	}
 
    public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('fixerio_forex_rates');
		
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('fixerio_forex_rates');
	}
	
	public function update_rates($route, $args){
		$this->load->model('setting/setting');
		
		$access_key = $this->config->get('module_fixerio_forex_rates_access_key');
		$enabled = $this->config->get('module_fixerio_forex_rates_status');
		$last_update = $this->config->get('module_fixerio_forex_rates_last_update');
		
		$base_currency = $this->config->get('config_currency');
		
		//Do nothing if extension is disabled or the access_key is empty
		if(!$enabled || strlen($access_key) == 0) return;
		
		$log = new Log('fixerio_forex_rates.log');
		
		$log->write('access_key:' . $access_key);
		$log->write('status:' . $enabled);
		
		//once per day
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency WHERE code != '" . $this->db->escape($this->config->get('config_currency')) . "' AND date_modified < '" .  $this->db->escape(date('Y-m-d H:i:s', strtotime('-1 day'))) . "'");
			
		//Day has not passed
		if(count($query->rows) == 0){
			$log->write('Will be updated after 24 hours');
			return;
		}
		
		$other_currencies = [];
		foreach ($query->rows as $result) {
			$other_currencies[] = $result['code'];
		}
		
		$csv_currency_list = implode($other_currencies, ',') . ',' . $base_currency;
		
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_URL, 'http://data.fixer.io/api/latest?access_key=' . $access_key . '&symbols=' . $csv_currency_list);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);

		$content = curl_exec($curl);
		$json = json_decode($content, true);
		
		if($content && $json['success'] == true){
			$base_currency_value = $json['rates'][$base_currency];
			
			foreach($other_currencies as $currency){
				$value = $json['rates'][$currency]/$base_currency_value;
				if ((float)$value) {
					$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '" . (float)$value . "', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($currency) . "'");
				}
			}

			$this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '1.00000', date_modified = '" .  $this->db->escape(date('Y-m-d H:i:s')) . "' WHERE code = '" . $this->db->escape($this->config->get('config_currency')) . "'");
			
			$this->cache->delete('currency');
		}
		
	}
}