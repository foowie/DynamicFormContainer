<?php

/**
 * Nette addon that allows dynamically add/remove set of items in Form (FormContainer)
 *
 * @author Daniel Robenek
 * @license MIT
 */

namespace Addons\Forms;

// <editor-fold defaultstate="collapsed" desc="use">

use \InvalidArgumentException;
use \InvalidStateException;
use Nette\Callback;
use Nette\Forms\FormContainer;
use Nette\Application\Presenter;
use \LogicException;
use Nette\ComponentContainer;
use Nette\IComponent;
use Nette\Forms\SubmitButton;

// </editor-fold>

class DynamicContainer extends DynamicContainerCore {

	// <editor-fold defaultstate="collapsed" desc="variables">

	/** array(add_delete_button?, label, name) */
	protected $deleteButtonOptions = array(true, "Odebrat", "deleteButton");
	/** array(add_add_button?, label, name) */
	protected $addButtonOptions = array(true, "Nový záznam", "addButton");
	/** @var bool Add ajaxClass to buttons? */
	protected $useAjax = true;
	/** @var string Ajax html class */
	protected $ajaxClass = "ajax";

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="constructor">

	/**
	 * Create FormContainer
	 * @param IComponentContainer $parent
	 * @param string $name
	 */
	public function __construct(IComponentContainer $parent = null, $name = null) {
		parent::__construct($parent, $name, null);
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="getters / setters">

	/**
	 * Set delete button options
	 * @param bool $enable Create delete button?
	 * @param string $label Button label
	 * @param string $name Button name
	 * @return DynamicContainer
	 */
	public function setDeleteButton($enable = true, $label = "Odebrat", $name = "deleteButton") {
		if($this->getRowCount() > 0)
			throw new InvalidStateException("Button options have to be set before factory is set and form is attached !");
		$this->deleteButtonOptions = array($enable, $label, $name);
		return $this;
	}

	/**
	 * Set add button options
	 * @param bool $enable Create add button?
	 * @param string $label Button label
	 * @param string $name Button name
	 * @return DynamicContainer
	 */
	public function setAddButton($enable = true, $label = "Nový záznam", $name = "addButton") {
		if($this->getRowCount() > 0)
			throw new InvalidStateException("Button options have to be set before factory is set and form is attached !");
		$this->addButtonOptions = array($enable, $label, $name);
		if ($enable) {
			if(!$this->lookup("Nette\Forms\FormContainer", false) === null)
				$this->attachAddButton();
		}
		return $this;
	}

	/**
	 * Use ajax / ajax class
	 * @param bool $useAjax
	 * @param string $ajaxClass
	 * @return DynamicContainer
	 */
	public function setAjax($useAjax = true, $ajaxClass = "ajax") {
		$this->useAjax = $useAjax;
		$this->ajaxClass = $ajaxClass;
		return $this;
	}

	/**
	 * Use ajax class to buttons?
	 * @param bool $useAjax
	 * @return DynamicContainer
	 */
	public function setUseAjax($useAjax) {
		$this->useAjax = $useAjax;
		return $this;
	}

	/**
	 * Class for ajax buttons
	 * @param string $ajaxClass
	 * @return DynamicContainer
	 */
	public function setAjaxClass($ajaxClass) {
		$this->ajaxClass = $ajaxClass;
		return $this;
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="other protected+ methods">

	/**
	 * Create "Add row" button, if enabled
	 */
	protected function attachAddButton() {
		list($enableAddButton, $addButtonLabel, $addButtonName) = $this->addButtonOptions;
		if ($enableAddButton) {
			$container = $this->getParent();
			if (isset($container[$addButtonName]))
				return; // todo: exception? (add param attached)
			$container->addComponent(new SubmitButton($addButtonLabel), $addButtonName, $this->getName());
			$container[$addButtonName]->setValidationScope(false)->onClick[] = callback($this, "addRow");
			if ($this->useAjax)
				$container[$addButtonName]->getControlPrototype()->class($this->ajaxClass);
		}
	}

	/**
	 * Overriden method createRow, added "Delete row" button
	 * @param string $name
	 */
	protected function createRow($name = null) {
		$innerContainer = parent::createRow($name);

		list($enableDeleteButton, $deleteButtonLabel, $deleteButtonName) = $this->deleteButtonOptions;
		if ($enableDeleteButton) {
			$button = $innerContainer->addSubmit($deleteButtonName, $deleteButtonLabel)->setValidationScope(false);
			$button->onClick[] = callback($this, "removeRowClickHandler");
			if ($this->useAjax)
				$button->getControlPrototype()->class($this->ajaxClass);
		}
	}

	/**
	 * Look at parent ;)
	 */
	protected function attached($obj) {
		parent::attached($obj);
		if ($obj instanceof Presenter) {
			$this->attachAddButton();
		}
	}

	/**
	 * Called when is clicked to "Remove row" button
	 * @param SubmitButton $button
	 */
	public function removeRowClickHandler($button) {
		$name = $button->getParent()->getName();
		$this->removeRow($name);
	}

	/**
	 * Call this before rendering
	 */
	public function beforeRender() {
		list($enableDeleteButton, $deleteButtonLabel, $deleteButtonName) = $this->deleteButtonOptions;
		if ($enableDeleteButton && $this->getRowCount() <= $this->minimumRows) {
			foreach ($this->getRows() as $container) {
				$button = $container[$deleteButtonName];
				$button->setDisabled()->getControlPrototype()->style("display: none;");
			}
		}

		list($enableAddButton, $addButtonLabel, $addButtonName) = $this->addButtonOptions;
		if ($enableAddButton && $this->getRowCount() >= $this->maximumRows) {
			$button = $this->parent[$addButtonName];
			$button->setDisabled()->getControlPrototype()->style("display: none;");
		}
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="register helpers">

	public static function FormContainer_addDynamicContainer(FormContainer $_this, $name = null) {
		return $_this[$name] = new DynamicContainer(null, $name);
	}

	public static function register($methodName = "addDynamicContainer") {
		if(PHP_VERSION_ID >= 50300)
			FormContainer::extensionMethod($methodName, "Addons\Forms\DynamicContainer::FormContainer_addDynamicContainer");
		else
			FormContainer::extensionMethod("FormContainer::$methodName", array("DynamicContainer", "FormContainer_addDynamicContainer"));
	}

	// </editor-fold>

}