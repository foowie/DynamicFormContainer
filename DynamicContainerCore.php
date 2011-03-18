<?php

/**
 * Nette addon that allows dynamically add/remove set of items in Form (FormContainer)
 *
 * @author Daniel Robenek
 * @license MIT
 */

namespace Addons\Forms;

// <editor-fold defaultstate="collapsed" desc="use">

use Nette\IComponentContainer;
use Nette\IComponent;
use Nette\Forms\FormContainer;
use Nette\Application\Presenter;
use \InvalidStateException;
use \LogicException;

// </editor-fold>

class DynamicContainerCore extends FormContainer {

	// <editor-fold defaultstate="collapsed" desc="variables">

	/** @var callback Function which create content of form container function(FormContainer $container, $dynamicContainer, $form) */
	private $factoryCallback;

	/** Restriction for rows count */
	protected $defaultRows = 1;
	protected $minimumRows = 2;
	protected $maximumRows = 100;


	/** @var array Registred onChange callbacks */
	public $onChange = array();

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="constructor">

	/**
	 * Create FormContainer
	 * @param IComponentContainer $parent
	 * @param string $name
	 * @param callback $factoryCallback
	 */
	public function __construct(IComponentContainer $parent = null, $name = null, $factoryCallback = null) {
		parent::__construct($parent, $name);
		$this->setFactory($factoryCallback);
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="getters / setters">

	/**
	 * Get number of rows (containers)
	 * @return int
	 */
	public function getRowCount() {
		return count($this->getComponents());
	}

	/**
	 * Get all dynamic rows
	 * @return Iterator of FormContainer
	 */
	public function getRows() {
		return $this->getComponents();
	}

	/**
	 * Set minimum number of container rows
	 * @param int $min
	 * @return $this
	 */
	public function setMinCount($min = 0) {
		$this->minimumRows = $min;
		return $this;
	}

	/**
	 * Set maximum number of container rows
	 * @param int $max
	 * @return $this
	 */
	public function setMaxCount($max = 0) {
		$this->maximumRows = $max;
		return $max;
	}


	/**
	 * Set default number of rows
	 * @param int $count
	 * @return $this
	 */
	public function setDefaultCount($count) {
		$this->defaultRows = $count;
		return $this;
	}

	/**
	 * Set callback which content add items into container
	 * @param Callback $callback function($container, $dynamicContainer, $form)
	 * @return $this
	 */
	public function setFactory($callback) {
		$this->factoryCallback = $callback;
		if($callback !== null)
			$this->monitor('Nette\Application\Presenter');
		return $this;
	}


	/**
	 * Public method for row adding
	 * Fires onSubmit
	 * @return bool Was inserting successfull? (false = max rows reached)
	 */
	public function addRow() {
		if($this->getRowCount() >= $this->maximumRows)
			return false;
		$this->createRow();
		$this->fireOnChange();
		return true;
	}

	/**
	 * Removes row of given name
	 * Fires onChange
	 * @param int|string $name
	 * @return bool true = ok false = minimum limit exceeded
	 * @throws InvalidArgumentException
	 */
	public function removeRow($name) {
		if(!isset($this[$name]))
			throw new InvalidArgumentException("Index doesnt exists '$name'");
		if($this->getRowCount() - 1 < $this->minimumRows)
			return false;
		unset($this[$name]);
		$this->fireOnChange();
		return true;
	}

	/**
	 * Add callback which is called on change inner rows count
	 * @param Callback $callback
	 */
	public function addOnChange($callback) {
		if(!is_callable($callback))
			throw new InvalidArgumentException("Not callable");
		$this->onChange[] = $callback;
	}

	/**
	 * Disallow user to add components
	 * @param IComponent $component
	 * @param String $name
	 */
	public function addComponent(IComponent $component, $name) {
		throw new LogicException("Use factory callback to add controls !");
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="other protected+ methods">

	/**
	 * Called when component is attached to monitored object
	 * @param Presenter $obj
	 */
	protected function attached($obj)	{
		if ($obj instanceof Presenter)
			$this->rebuild();
		parent::attached($obj);
	}

	/**
	 * Remove all subcontainers
	 */
	protected function clearRows() {
		foreach($this->getRows() as $name => $component)
			unset($this[$name]);
	}

	/**
	 * Rebuild containers structure
	 */
	protected function rebuild() {
		if($this->getForm()->isSubmitted()) {
			$this->clearRows();
			$containerPath = explode("-", $this->lookupPath("Nette\Forms\Form"));
			$containerData = $this->getForm()->getHttpData();
			foreach($containerPath as $step) { // goes through structure and extract neccesery data
				if(!isset($containerData[$step]))
					return $this->fixMinRows(); // empty
				$containerData = $containerData[$step];
			}
			ksort($containerData);
			foreach($containerData as $innerContainerName => $innerContainerValues)
				$this->createRow($innerContainerName);
		} else {
			$this->fixDefaultRows();
		}
		$this->fixMinRows();
	}

	/**
	 * Create minimum count of rows if neccessery
	 */
	protected function fixMinRows() {
		$count = $this->getRowCount();
		if($count < $this->minimumRows) {
			for($i = $count; $i < $this->minimumRows; $i++)
				$this->createRow();
		}
	}

	/**
	 * Create default count of rows if neccessery
	 */
	protected function fixDefaultRows() {
		$count = $this->getRowCount();
		if($count < $this->defaultRows) {
			for($i = $count; $i < $this->defaultRows; $i++)
				$this->createRow();
		}
	}

	/**
	 * Creates row of given name
	 * @param string|int $name
	 * @return FormContainer created container
	 */
	protected function createRow($name = null) {
		$items = $this->getRows();
		$itemCount = count($items);
		if($name === null) {
			$name = $itemCount == 0 ? "0" : (string)(1 + (int)(end($items)->getName()));
		}
		$innerContainer = $this->addPrivateContainer($name);

		call_user_func($this->factoryCallback, $innerContainer, $this, $this->getForm());

		return $innerContainer;
	}

	/**
	 * For internal use, add FormContainer of given name
	 * @param String $name
	 * @return FormContainer added contanier
	 */
	private function addPrivateContainer($name) {
		$control = new FormContainer();
		$control->currentGroup = $this->currentGroup;
		parent::addComponent($control, $name);
		return $this[$name];
	}

	/**
	 * Call all callback which are registred
	 */
	protected function fireOnChange() {
		foreach($this->onChange as $onChange)
			call_user_func($onChange);
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="register helpers">

	public static function FormContainer_addDynamicContainerCore(FormContainer $_this, $name = null, $factoryCallback = null) {
		return $_this[$name] = new DynamicContainerCore(null, $name, $factoryCallback);
	}

	public static function register($methodName = "addDynamicContainer") {
		if(PHP_VERSION_ID >= 50300)
			FormContainer::extensionMethod($methodName, "Addons\Forms\DynamicContainerCore::FormContainer_addDynamicContainerCore");
		else
			FormContainer::extensionMethod("FormContainer::$methodName", array("DynamicContainerCore", "FormContainer_addDynamicContainerCore"));
	}

	// </editor-fold>

}