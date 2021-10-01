<?php

/* * ***************************Includes********************************* */
// require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class JeedomConnectWidget extends config {

	public static $_plugin_id = 'JeedomConnect';

	public static function getMaxIndex() {

		return config::byKey('index::max', self::$_plugin_id) ?: '0';
	}

	public static function incrementIndex() {

		log::add(self::$_plugin_id, 'debug', 'increment widget index ');
		$current = config::byKey('index::max', self::$_plugin_id) ?: '0';
		log::add(self::$_plugin_id, 'debug', 'current index : ' . $current);

		$next = intval($current) + 1;
		config::save('index::max', strval($next), self::$_plugin_id);
		log::add(self::$_plugin_id, 'debug', 'incrementIndex done');
		return $next;
	}

	public static function setConfiguration($_widgetId, $_key, $_value) {

		$currentConf = self::getConfiguration($_widgetId);
		log::add(self::$_plugin_id, 'debug', ' ## current conf ' . json_encode($currentConf));
		$conf = utils::setJsonAttr($currentConf, $_key, $_value);
		log::add(self::$_plugin_id, 'debug', ' ## conf saved ' . json_encode($conf));
		return self::saveConfig($conf, $_widgetId);
	}

	public static function getConfiguration($_widgetId, $_key = '', $_default = '') {

		$conf = self::byKey('widget::' . $_widgetId, self::$_plugin_id);
		// log::add(self::$_plugin_id, 'info', ' ##  getConfiguration -- id '. $_widgetId. ' = retrieved : ' . json_encode($conf) ) ;
		return utils::getJsonAttr($conf, $_key, $_default);
	}

	public static function getJsonData($_data, $_key = '', $_default = '') {

		// log::add(self::$_plugin_id, 'info', ' ##  getJsonData  -- data received => ' . json_encode($_data) );
		$conf = json_encode($_data);
		return utils::getJsonAttr($conf, $_key, $_default);
	}

	public static function getAllConfigurations() {

		$result = array();
		foreach (config::searchKey('widget', self::$_plugin_id)  as $config) {
			$newConf['key'] = $config['key'];
			$newConf['id'] = str_replace('widget::', '', $config['key']);
			$newConf['conf'] = $config['value'];

			array_push($result, $newConf);
		}
		return $result;
	}

	public static function getWidgets($_id = 'all', $_fullConfig = true) {

		if ($_id === 'all') {
			if ($_fullConfig) log::add(self::$_plugin_id, 'debug', 'getWidgets for all widget');
			$widgets = JeedomConnectWidget::getAllConfigurations();
		} else {
			$widgets = array();
			$tmp = JeedomConnectWidget::getConfiguration($_id, '', null);
			if (!empty($tmp)) {
				//log::add(self::$_plugin_id, 'debug', ' tmp result for '.$_id. '>' . json_encode($tmp) . '<' );
				$tmp['conf'] = $tmp;
				array_push($widgets, $tmp);
			}
		}

		$widgetArray = array();
		if (!empty($widgets)) {
			foreach ($widgets as $widget) {
				$widgetItem = array();

				$widgetItem['img'] = $widget['conf']['imgPath'] ?: plugin::byId(self::$_plugin_id)->getPathImgIcon();

				$widgetJC = json_decode($widget['conf']['widgetJC'], true);
				if ($_fullConfig) $widgetItem['widgetJC'] = $widget['conf']['widgetJC'] ?? '';
				$widgetItem['enable'] = $widgetJC['enable'];
				$widgetItem['name'] = $widgetJC['name'] ?? 'inconnu';
				$widgetItem['type'] = $widgetJC['type'] ?? 'none';
				$widgetItem['roomId'] = $widgetJC['room'] ?? '';
				$widgetRoomObjet = jeeObject::byId($widgetItem['roomId']);
				$widgetItem['roomName'] = (!is_null($widgetRoomObjet) && is_object($widgetRoomObjet)) ? ($widgetItem['roomId'] == 'global' ? 'Global' : $widgetRoomObjet->getName()) : 'Aucun';
				$widgetItem['id'] = $widgetJC['id'] ?? 'none';

				array_push($widgetArray, $widgetItem);
			}

			$roomName  = array_column($widgetArray, 'roomName');
			$widgetName = array_column($widgetArray, 'name');

			array_multisort($roomName, SORT_ASC, $widgetName, SORT_ASC, $widgetArray);

			//log::add(self::$_plugin_id, 'debug', ' final result sent >' . json_encode($widgetArray) );
		}
		return $widgetArray;
	}

	public static function getWidgetsList() {

		$widgetArray = self::getWidgets('all', true);
		usort($widgetArray, function ($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		return $widgetArray;
	}

	public static function updateImgPath($widgetId, $newPath, $reload = true) {
		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			log::add(self::$_plugin_id, 'debug', 'updateImgPath - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		$widgetSettings['imgPath'] = $newPath;
		self::saveConfig($widgetSettings, $widgetId);
		log::add(self::$_plugin_id, 'debug', 'updateImgPath - img path updated to "' . $newPath . '" for widget id [' . $widgetId . ']');
		if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
		return;
	}

	public static function updateConfig($widgetId, $key, $value, $reload = true) {

		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			log::add(self::$_plugin_id, 'debug', 'updateConfig - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		$widgetJC = json_decode($widgetSettings['widgetJC'], true);
		if (isset($widgetJC[$key])) {
			$widgetJC[$key] = $value;
			$widgetSettings['widgetJC'] = json_encode($widgetJC);

			log::add(self::$_plugin_id, 'debug', 'updateConfig - key "' . $key . '" found - updating details id [' . $widgetId . '] - conf : ' . $value);
			self::saveConfig($widgetSettings, $widgetId);
			if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
			return;
		}
		log::add(self::$_plugin_id, 'debug', 'updateConfig - key ' . $key . ' NOT found');
	}

	public static function updateWidgetConfig($config) {
		$widgetId = $config['id'];
		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			log::add(self::$_plugin_id, 'debug', 'updateWidgetConfig - widgetId ' . $widgetId . ' NOT found');
			return;
		}
		$widgetSettings['widgetJC'] = json_encode($config);
		self::saveConfig($widgetSettings, $widgetId);
		JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
	}

	public static function saveConfig($conf, $widgetId = null) {

		$cpl = '';
		if (is_null($widgetId)) {
			$widgetId = self::incrementIndex();
			$cpl = ' [new creation]';
		}

		log::add(self::$_plugin_id, 'debug', 'saveConfiguration details received for id : ' . $widgetId . $cpl . ' - conf : ' . json_encode($conf));
		config::save('widget::' . $widgetId, $conf, self::$_plugin_id);
		log::add(self::$_plugin_id, 'debug', 'saveConfiguration done');
		return $widgetId;
	}

	public static function removeWidget($idToRemove = '') {
		if (!$idToRemove) {
			log::add('JeedomConnect', 'warning', 'Removing widget(s) -- no data received -- abort');
			return;
		}

		if (is_array($idToRemove)) {
			log::add('JeedomConnect', 'info', 'Removing widget(s) -- data received : (array) ' . json_encode($idToRemove));
			$arrayIdToRemove = $idToRemove;
		} else {
			log::add('JeedomConnect', 'info', 'Removing widget -- data received : (int) ' . json_encode($idToRemove));
			$arrayIdToRemove = array($idToRemove);
		}


		// remove the widget ID inside json file config of each JC equipement
		foreach (JeedomConnect::byType('JeedomConnect') as $eqLogic) {
			$apiKey = $eqLogic->getConfiguration('apiKey');
			if ($apiKey !=  '') {
				$eqLogic->removeWidgetConf($arrayIdToRemove);
			}
		}

		// remove the widget ID inside widgets of a widget (favourite, group, ...)
		$allWidgets = self::getAllConfigurations();
		log::add('JeedomConnect', 'info', 'all widget : ' . json_encode($allWidgets));
		foreach ($allWidgets as $widget) {
			$hasChanged = false;
			$conf = json_decode($widget['conf']['widgetJC'], true);

			if (!array_key_exists('widgets', $conf) && !array_key_exists('moreWidgets', $conf)) {
				continue;
			}

			if (isset($conf['widgets'])) {
				foreach ($conf['widgets'] as $index => $obj) {

					if (in_array($obj['id'],  $arrayIdToRemove)) {
						log::add('JeedomConnect', 'info', 'removing obj id [widgets] : ' .  $obj['id'] . ' at index ' . $index . ' for parent ' . $widget['id']);
						unset($conf['widgets'][$index]);
						$hasChanged = true;
					}
				}
				$conf['widgets'] = array_values($conf['widgets']);
			}

			if (isset($conf['moreWidgets'])) {
				foreach ($conf['moreWidgets'] as $index => $obj) {

					if (in_array($obj['id'],  $arrayIdToRemove)) {
						log::add('JeedomConnect', 'info', 'removing obj id [moreWidgets] : ' .  $obj['id'] . ' at index ' . $index . ' for parent ' . $widget['id']);
						unset($conf['moreWidgets'][$index]);
						$hasChanged = true;
					}
				}
				$conf['moreWidgets'] = array_values($conf['moreWidgets']);
			}


			if ($hasChanged) self::setConfiguration(str_replace('widget::', '', $widget['id']), 'widgetJC', json_encode($conf));
		}

		foreach ($arrayIdToRemove as $idToRemove) {
			self::removeWidgetConf('widget::' . $idToRemove);
		}

		return;
	}

	public static function removeWidgetConf($idToRemove) {
		log::add(self::$_plugin_id, 'debug', 'removing widget id : ' . $idToRemove);
		self::remove($idToRemove, self::$_plugin_id);
	}

	public static function duplicateWidget($widgetId) {

		log::add(self::$_plugin_id, 'debug', 'duplicating widget id : ' . $widgetId);
		$configInit = self::getConfiguration($widgetId);

		$config = json_decode($configInit, true);

		$newId = self::incrementIndex();
		$config['widgetJC']['id'] = $newId;

		self::saveConfig(json_encode($config), $newId);
		return $newId;
	}

	public static function countWidgetByEq() {

		// init an array with all available widget Id
		$allWidgets = self::getAllConfigurations();
		$widgetList = array();
		foreach ($allWidgets as $widget) {
			if (!array_key_exists($widget['id'], $widgetList))  $widgetList[$widget['id']] = 0;

			if (array_key_exists('widgets', $widget)) {
				foreach ($widget['widgets'] as $sousWidget) {
					if (!array_key_exists($sousWidget['id'], $widgetList)) {
						$widgetList[$sousWidget['id']] = 0;
					}
				}
			}
		}

		$unexistingId = array();
		foreach (\eqLogic::byType('JeedomConnect') as $eqLogic) {
			if ($eqLogic->getIsEnable()) {

				$conf = $eqLogic->getConfig(true);

				foreach ($conf['payload']['widgets'] as $key => $value) {
					if (array_key_exists($value['id'], $widgetList)) {
						$widgetList[$value['id']]++;
					} else {
						array_push($unexistingId, $value['id']);
					}
				}
			} else {
				log::add(self::$_plugin_id, 'debug', 'Equipment excluded because disable : ' . $eqLogic->getName() . ' [' . $eqLogic->getId() . ']');
			}
		}

		$onlyUnused  = array_filter($widgetList, function ($k) {
			return $k == 0;
		});

		log::add(self::$_plugin_id, 'debug', 'final result count : ' . json_encode($widgetList));
		log::add(self::$_plugin_id, 'debug', 'onlyUnused   : ' . json_encode(array_keys($onlyUnused)));
		log::add(self::$_plugin_id, 'debug', 'list of unexisting widget Id : ' . json_encode($unexistingId));

		return array('count' => $widgetList, 'unused' => array_keys($onlyUnused), 'unexisting' => $unexistingId);
	}

	public static function uploadWidgetConf($dataJson) {
		log::add(self::$_plugin_id, 'debug', 'uploading widgets conf');

		try {
			$allConf = json_decode($dataJson, true);

			foreach ($allConf as $conf) {
				log::add(self::$_plugin_id, 'debug', ' import key => ' . $conf['key'] . ' // value ==> ' . json_encode($conf['value']));

				if (!is_int($conf['value'])) $conf['value'] = json_encode($conf['value']);

				config::save($conf['key'], $conf['value'], self::$_plugin_id);
			}

			return true;
		} catch (Exception $e) {
			log::add('JeedomConnect', 'error', 'Unable to upload config file : ' . $e->getMessage());
			throw new Exception("Error avec l'import");
		}
	}

	public static function exportWidgetConf() {
		$export_file = JeedomConnect::$_config_dir . "export_Widgets.json";

		$result = array();

		$maxId = self::getMaxIndex();
		array_push($result, array('key' => 'index::max', 'value' => intval($maxId)));

		$allWidgets = self::getAllConfigurations();

		foreach ($allWidgets as $widget) {
			array_push($result, array('key' => $widget['key'], 'value' => $widget['conf']));
		}

		try {
			log::add('JeedomConnect', 'debug', 'Saving widgets conf file : ' . $export_file);
			file_put_contents($export_file, json_encode($result, JSON_PRETTY_PRINT));
		} catch (Exception $e) {
			log::add('JeedomConnect', 'error', 'Unable to write file : ' . $e->getMessage());
		}
	}

	//***************  EXPERIMENTAL ZONE  =) ****************************/

	public static function replaceTextConfig($widgetId, $searchAndReplace, $reload = true) {
		//$searchAndReplace = array("icon_user.png" => "icon_userSSS.png", "pas_content.png" => "hyper_content.png");

		$widgetSettings = self::getConfiguration($widgetId);
		if (empty($widgetSettings)) {
			log::add(self::$_plugin_id, 'debug', 'replaceTextConfig - widgetId ' . $widgetId . ' NOT found');
			return;
		}

		$widgetJC = json_decode($widgetSettings['widgetJC'], true);
		try {
			self::replaceJC($widgetJC, $searchAndReplace);

			$widgetSettings['widgetJC'] = json_encode($widgetJC);
			self::saveConfig($widgetSettings, $widgetId);
			if ($reload) JeedomConnect::checkAllEquimentsAndUpdateConfig($widgetId);
			return;
		} catch (Exception $e) {
			log::add('JeedomConnect', 'error', 'replaceTextConfig - ' . $e->getMessage());
		}
	}

	public static function replaceJC(&$array, $replaces) {
		foreach ($array as $k => $v) {
			$new_k = self::replaceJC_word($k, $replaces);
			if (is_array($v)) {
				self::replaceJC($v, $replaces);
			} else {
				$v = self::replaceJC_word($v, $replaces);
			}
			$array[$new_k] = $v;
			if ($new_k != $k) {
				unset($array[$k]);
			}
		}
	}

	public static function replaceJC_word($word, $replaces) {
		if (array_key_exists($word, $replaces)) {
			$word = str_replace($word, $replaces[$word], $word);
		}
		return $word;
	}



	public static function checkCmdSetupInWidgets() {
		//get all widgets saved in DB
		$widgetsDb = self::getWidgets();

		// get setup of every type of widget
		$widgetParam = JeedomConnect::getWidgetParam(false);

		$cmdArrayError = array();

		foreach ($widgetsDb as $item) {
			$widget = json_decode($item['widgetJC'], true);

			$config = $widgetParam[$widget['type']];
			foreach ($config['options'] as $option) {
				// will check only the cmd data
				if (!in_array($option['category'], array("cmd", "cmdList"))) {
					continue;
				}

				// log::add('JeedomConnect', 'debug', ' cmd name ' . $option['name'] . '  - matching widget data : ' . json_encode($widget[$option['id']]));
				if ($option['category'] == "cmd") {
					if (array_key_exists($option['id'], $widget)) {
						$cmdWidgetId = $widget[$option['id']]['id'] ?: null;
						if (!self::isCmd($cmdWidgetId) && !in_array($widget['id'], $cmdArrayError)) {
							$cmdArrayError[] = $widget['id'];
						}
					}
				} elseif ($option['category'] == "cmdList") {
					if (array_key_exists('actions', $widget)) {
						foreach ($widget['actions'] as $action) {
							$cmdWidgetId = $action['id'] ?: null;
							if (!self::isCmd($cmdWidgetId) && !in_array($widget['id'], $cmdArrayError)) {
								$cmdArrayError[] = $widget['id'];
							}
						}
					}
				}
			}
		}

		// log::add('JeedomConnect', 'debug', ' ## all errors :    ' . json_encode($cmdArrayError));
		return $cmdArrayError;
	}


	public static function isCmd($id) {

		if (!is_null($id)) {
			$cmd = cmd::byId($id);
			if (!is_object($cmd)) {
				return false;
			}
		}
		return true;
	}
}
