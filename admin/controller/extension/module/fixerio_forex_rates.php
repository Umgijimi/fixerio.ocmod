<?php
class ControllerExtensionModuleFixerioForexRates extends Controller {
	
	private $version = '0.0.1';
    private $error = array();
  
    public function index() {
		$this->load->language('extension/module/fixerio_forex_rates');
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
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
	}
 
    public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('fixerio_forex_rates');
	}
}