<?php
class ControllerExtensionModuleFixerioForexRates extends Controller {
	
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