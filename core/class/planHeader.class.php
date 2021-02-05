<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../core/php/core.inc.php';

class planHeader {
	/*     * *************************Attributs****************************** */

	private $id;
	private $name;
	private $image;
	private $configuration;
	private int $order = 9999;
	private bool $_changed = false;

	/*     * ***********************Méthodes statiques*************************** */

    /**
     * @param $_id
     * @return array|null
     * @throws ReflectionException
     * @throws Exception
     */
    public static function byId($_id): ?array
    {
		$values = array(
			'id' => $_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM planHeader
		WHERE id=:id';
		return DB::Prepare($sql, $values, DB::FETCH_TYPE_ROW, PDO::FETCH_CLASS, __CLASS__);
	}

    /**
     * @return array|null
     * @throws ReflectionException
     * @throws Exception
     */
    public static function all(): ?array
    {
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . '
		FROM planHeader
		ORDER BY `order`';
		return DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
	}

    /**
     *
     * @param $_type
     * @param $_id
     * @return array
     * @throws ReflectionException
     */
	public static function searchByUse($_type, $_id): array
    {
		$return = array();
		$search = '#' . str_replace('cmd', '', $_type . $_id) . '#';
		$plans = array_merge(plan::byLinkTypeLinkId($_type, $_id), plan::searchByConfiguration($search, 'eqLogic'));
		foreach ($plans as $plan) {
			$planHeader = $plan->getPlanHeader();
			if(!is_object($planHeader)){
				continue;
			}
			$return[$planHeader->getId()] = $planHeader;
		}
		return $return;
	}

	/*     * *********************Méthodes d'instance************************* */

    /**
     * @param string $_format
     * @param array $_parameters
     * @return string
     * @throws Exception
     */
    public function report($_format = 'pdf', $_parameters = array()): string
    {
		$url = network::getNetworkAccess('internal') . '/index.php?v=d&p=plan';
		$url .= '&plan_id=' . $this->getId();
		$url .= '&report=1';
		if (isset($_parameters['arg']) && trim($_parameters['arg']) != '') {
			$url .= '&' . $_parameters['arg'];
		}
		return report::generate($url, 'plan', $this->getId(), $_format, $_parameters);
	}

    /**
     * @param $_name
     * @return planHeader
     * @throws ReflectionException
     * @throws Exception
     */
    public function copy($_name): planHeader
    {
		$planHeaderCopy = clone $this;
		$planHeaderCopy->setName($_name);
		$planHeaderCopy->setId('');
		$planHeaderCopy->save();
		foreach(($this->getPlan()) as $plan) {
			$planCopy = clone $plan;
			$planCopy->setId('');
			$planCopy->setPlanHeader_id($planHeaderCopy->getId());
			$planCopy->save();
		}
		$filename1 = 'planHeader'.$this->getId().'-'.$this->getImage('sha512') . '.' . $this->getImage('type');
		if(file_exists(__DIR__.'/../../data/plan/'.$filename1)){
			$filename2 = 'planHeader'.$planHeaderCopy->getId().'-'.$planHeaderCopy->getImage('sha512') . '.' . $planHeaderCopy->getImage('type');
			copy(__DIR__.'/../../data/plan/'.$filename1,__DIR__.'/../../data/plan/'.$filename2);
		}
		return $planHeaderCopy;
	}

    /**
     * @throws Exception
     */
    public function preSave() {
		if (trim($this->getName()) == '') {
			throw new Exception(__('Le nom du plan ne peut pas être vide', __FILE__));
		}
		if ($this->getConfiguration('desktopSizeX') == '') {
			$this->setConfiguration('desktopSizeX', 500);
		}
		if ($this->getConfiguration('desktopSizeY') == '') {
			$this->setConfiguration('desktopSizeY', 500);
		}
		if ($this->getConfiguration('backgroundTransparent') == '') {
			$this->setConfiguration('backgroundTransparent', 1);
		}
		if ($this->getConfiguration('backgroundColor') == '') {
			$this->setConfiguration('backgroundColor', '#ffffff');
		}
	}

    /**
     * @throws Exception
     */
    public function save() {
		DB::save($this);
	}

    /**
     * @throws Exception
     */
    public function remove() {
		jeedom::addRemoveHistory(array('id' => $this->getId(), 'name' => $this->getName(), 'date' => date('Y-m-d H:i:s'), 'type' => 'plan'));
		DB::remove($this);
	}

    /**
     * @return string
     */
    public function displayImage(): string
    {
		if ($this->getImage('sha512') == '') {
			return '';
		}
		$size = $this->getImage('size');
		$filename = 'planHeader'.$this->getId().'-'.$this->getImage('sha512') . '.' . $this->getImage('type');
		//return '<img style="z-index:997" src="data/plan/' . $filename . '" data-sixe_y="' . $size[1] . '" data-sixe_x="' . $size[0] . '">';
		return '<div style="z-index:997;background:url(data/plan/' . $filename . ');background-position:center;width:'.$this->getConfiguration('desktopSizeX').'px;height:'.$this->getConfiguration('desktopSizeY').'px;background-size:cover;" data-sixe_y="' . $size[1] . '" data-sixe_x="' . $size[0] . '"></div>';
	}

    /**
     * @return array|null
     * @throws ReflectionException
     */
    public function getPlan(): ?array
    {
		return plan::byPlanHeaderId($this->getId());
	}

    /**
     * @param array[] $_data
     * @param int $_level
     * @param int $_drill
     * @return array|array[]|void
     */
    public function getLinkData(&$_data = array('node' => array(), 'link' => array()), $_level = 0, $_drill = 3): array
    {
		if (isset($_data['node']['plan' . $this->getId()])) {
			return;
		}
		$_level++;
		if ($_level > $_drill) {
			return $_data;
		}
		$icon = findCodeIcon($this->getConfiguration('icon','<i class="fas fa-paint-brush"></i>'));
		$_data['node']['plan' . $this->getId()] = array(
			'id' => 'plan' . $this->getId(),
			'type' => __('Design',__FILE__),
			'name' => substr($this->getName(), 0, 20),
			'icon' => $icon['icon'],
			'fontfamily' => $icon['fontfamily'],
			'fontsize' => '1.5em',
			'fontweight' => ($_level == 1) ? 'bold' : 'normal',
			'texty' => -14,
			'textx' => 0,
			'title' => __('Design :', __FILE__) . ' ' . $this->getName(),
			'url' => 'index.php?v=d&p=plan&plan_id=' . $this->getId(),
		);
	}

	/*     * **********************Getteur Setteur*************************** */

    /**
     * @return mixed
     */
    public function getId() {
		return $this->id;
	}

    /**
     * @return mixed
     */
    public function getName() {
		return $this->name;
	}

    /**
     * @return int|string
     */
    public function getOrder() {
		if ($this->order == '' || !is_numeric($this->order)) {
			return 0;
		}
		return $this->order;
	}

    /**
     * @param $_id
     * @return $this
     */
    public function setId($_id): planHeader
    {
		$this->_changed = utils::attrChanged($this->_changed,$this->id,$_id);
		$this->id = $_id;
		return $this;
	}

    /**
     * @param $_name
     * @return $this
     */
    public function setName($_name): planHeader
    {
		$this->_changed = utils::attrChanged($this->_changed,$this->name,$_name);
		$this->name = $_name;
		return $this;
	}

    /**
     * @param $_order
     * @return $this
     */
    public function setOrder($_order): planHeader
    {
		$this->_changed = utils::attrChanged($this->_changed,$this->order,$_order);
		$this->order = $_order;
		return $this;
	}

    /**
     * @param string $_key
     * @param string $_default
     * @return array|bool|mixed|string
     */
    public function getImage($_key = '', $_default = '') {
		return utils::getJsonAttr($this->image, $_key, $_default);
	}

    /**
     * @param $_key
     * @param $_value
     * @return $this
     */
    public function setImage($_key, $_value): planHeader
    {
		$image = utils::setJsonAttr($this->image, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed,$this->image,$image);
		$this->image = $image;
		return $this;
	}

    /**
     * @param string $_key
     * @param string $_default
     * @return array|bool|mixed|string
     */
    public function getConfiguration($_key = '', $_default = '') {
		return utils::getJsonAttr($this->configuration, $_key, $_default);
	}

    /**
     * @param $_key
     * @param $_value
     * @return $this
     */
    public function setConfiguration($_key, $_value): planHeader
    {
		if ($_key == 'accessCode' && $_value != '' && !is_sha512($_value)) {
			$_value = sha512($_value);
		}
		$configuration = utils::setJsonAttr($this->configuration, $_key, $_value);
		$this->_changed = utils::attrChanged($this->_changed,$this->configuration,$configuration);
		$this->configuration =$configuration;
		return $this;
	}

    /**
     * @return bool
     */
    public function getChanged(): bool
    {
		return $this->_changed;
	}

    /**
     * @param $_changed
     * @return $this
     */
    public function setChanged($_changed): planHeader
    {
		$this->_changed = $_changed;
		return $this;
	}

}
