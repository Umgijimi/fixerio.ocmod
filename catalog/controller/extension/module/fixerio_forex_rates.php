<?php
class ControllerExtensionModuleFixerioForexRates extends Controller {
	/*
	* Update forex rates
	*/
	public function update_rates($route, $args){
		$access_key = $this->config->get('module_fixerio_forex_rates_access_key');
		$enabled = $this->config->get('module_fixerio_forex_rates_status');
		$last_update = $this->config->get('module_fixerio_forex_rates_last_update');
		$update_frequency = $this->config->get('module_fixerio_forex_rates_update_frequency');

		$base_currency = $this->config->get('config_currency');
		
		//Do nothing if extension is disabled or the access_key is empty
		if(!$enabled || strlen($access_key) == 0) return;
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "currency WHERE code != '" . $this->db->escape($this->config->get('config_currency')) . "' AND date_modified < '" .  $this->db->escape(date('Y-m-d H:i:s', strtotime("-$update_frequency hours"))) . "'");
			
		//Nothing to update. Happens when update_frequency has not passed.
		if(count($query->rows) == 0){
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

			//Set the time of the last update 
			$this->editSettingValue('module_fixerio_forex_rates', 'module_fixerio_forex_rates_last_update', date('Y-m-d H:i:s'));
		}
		
	}
	
	//Taken from admin/models/settings/settings.php
	private function editSettingValue($code = '', $key = '', $value = '', $store_id = 0) {
		if (!is_array($value)) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', serialized = '0'  WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
		} else {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape(json_encode($value)) . "', serialized = '1' WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
		}
	}
}