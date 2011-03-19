<?php

/**
 * Form macros
 *
 * @author Jan Marek, Daniel Robenek
 * @license MIT
 */

namespace Addons\Forms;

// <editor-fold defaultstate="collapsed" desc="use">

use \LogicException;
use \InvalidArgumentException;
use Nette\Templates\LatteMacros;
use Nette\String;
use Nette\Forms\Form;
use Nette\Web\Html;

// </editor-fold>

class FormMacros extends \Nette\Object {

	// <editor-fold defaultstate="collapsed" desc="variables">

	protected static $stack;

	/** @var LatteMacros */
	protected static $latteMacros = null;

	public static $defaultOuterError = "div class='form-errors'";
	public static $defaultInnerError = "p class='error'";

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="constructor">

	public function __construct() {
		throw new \LogicException("Static class could not be instantiated !");
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{form}">

	public static function macroFormBegin($content) {
		list($name, $modifiers) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::formBegin($name, \$control, $modifiers)";
	}
	public static function formBegin($form, $control, $modifiers) {
		$form = ($form instanceof Form) ?: $control[$form];
		self::$stack = array($form);
		self::applyModifiers($form->getElementPrototype(), $modifiers);
		$form->render("begin");
	}

	public static function macroFormEnd($content) {
		return "Addons\Forms\FormMacros::formEnd()";
	}
	public static function formEnd() {
		self::getForm()->render("end");
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{formErrors}">

	public static function macroFormErrors($content) {
		$latteMacros = self::getLatteMacros();
		$params = $latteMacros->formatArray($content);
		return "Addons\Forms\FormMacros::formErrors($params)";
	}
	public static function formErrors($parameters) { // todo: refactor
		$innerHtml = !empty($parameters) ? array_shift($parameters) : null;
		if($innerHtml === null)
			$innerHtml = Html::el(self::$defaultInnerError);
		else if(!($innerHtml instanceof Html))
			$innerHtml = Html::el($innerHtml);

		$outerHtml = !empty($parameters) ? array_shift($parameters) : null;
		if($outerHtml === null)
			$outerHtml = Html::el(self::$defaultOuterError);
		else if(!($outerHtml instanceof Html))
			$outerHtml = Html::el($outerHtml);

		$errors = self::getForm()->getErrors();
		if(empty($errors))
			return;

		foreach($errors as $error) {
			$currentInner = clone($innerHtml);
			$currentInner->setText($error);
			$outerHtml->add($currentInner);
		}
		echo($outerHtml->render());
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{formContainer}">

	public static function macroBeginContainer($content) {
		list($name) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::beginContainer($name)";
	}
	public static function beginContainer($name) {
		self::$stack[] = self::getControl($name);
	}
	
	public static function macroEndContainer($content) {
		return "Addons\Forms\FormMacros::endContainer()";
	}
	public static function endContainer() {
		array_pop(self::$stack);
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{input}">

	public static function macroInput($content) {
		list($name, $modifiers) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::input($name, $modifiers)";
	}
	public static function input($name, $modifiers) {
		$input = self::getControl($name)->getControl();
		self::applyModifiers($input, $modifiers);
		echo $input;
	}

	public static function macroPair($content) {
		list($name, $modifiers) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::pair($name, $modifiers)";
	}
	public static function pair($name, $modifiers) {
		$control = self::getControl($name);
		echo self::getForm()->getRenderer()->renderPair($control);
	}



	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{label}">

	public static function macroLabel($content) {
		list($name, $modifiers) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::label($name, $modifiers)";
	}
	public static function label($name, $modifiers) {
		$label = self::getControl($name)->getLabel();
		self::applyModifiers($label, $modifiers);
		echo $label;
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{inputValue}">

	public static function macroInputValue($content) {
		list($name, $modifiers) = self::fetchNameAndModifiers($content);
		return "Addons\Forms\FormMacros::inputValue($name, $modifiers)";
	}
	public static function inputValue($name, $modifiers = array()) {
		$input = self::getControl($name)->getControl();
		echo $input->getValue();
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="{dynamicContainer}">

	public static function macroBeginDynamicContainer($content) {
		list($name) = self::fetchNameAndModifiers($content);
		return '$dynamicContainers = Addons\Forms\FormMacros::getControl('.$name.')->getComponents(); Addons\Forms\FormMacros::beginContainer('.$name.'); foreach($dynamicContainers as $dynamicContainerName => $dynamicContainer): Addons\Forms\FormMacros::beginContainer($dynamicContainerName);';
	}
	public static function macroEndDynamicContainer($content) {
		return "Addons\Forms\FormMacros::endContainer(); endforeach; Addons\Forms\FormMacros::endContainer();";
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Helpers">

	public static function register() {
		LatteMacros::$defaultMacros["form"] = '<?php %Addons\Forms\FormMacros::macroFormBegin% ?>';
		LatteMacros::$defaultMacros["/form"] = '<?php %Addons\Forms\FormMacros::macroFormEnd% ?>';

		LatteMacros::$defaultMacros["formErrors"] = '<?php %Addons\Forms\FormMacros::macroFormErrors% ?>';

		LatteMacros::$defaultMacros["pair"] = '<?php %Addons\Forms\FormMacros::macroPair% ?>';
		LatteMacros::$defaultMacros["input"] = '<?php %Addons\Forms\FormMacros::macroInput% ?>';
		LatteMacros::$defaultMacros["label"] = '<?php %Addons\Forms\FormMacros::macroLabel% ?>';
		LatteMacros::$defaultMacros["inputValue"] = '<?php %Addons\Forms\FormMacros::macroInputValue% ?>';

		LatteMacros::$defaultMacros["formContainer"] = '<?php %Addons\Forms\FormMacros::macroBeginContainer% ?>';
		LatteMacros::$defaultMacros["/formContainer"] = '<?php %Addons\Forms\FormMacros::macroEndContainer% ?>';

		LatteMacros::$defaultMacros["dynamicContainer"] = '<?php %Addons\Forms\FormMacros::macroBeginDynamicContainer% ?>';
		LatteMacros::$defaultMacros["/dynamicContainer"] = '<?php %Addons\Forms\FormMacros::macroEndDynamicContainer% ?>';
	}

	/**
	 * Return instance of LatteMacros
	 * @return LatteMacros
	 */
	public static function getLatteMacros() {
		if(self::$latteMacros === null)
			self::$latteMacros = new LatteMacros();
		return self::$latteMacros;
	}

	/**
	 * Return current rendered form
	 * @return Form
	 */
	public static function getForm() {
		return self::$stack[0];
	}

	/**
	 * Return form control of given name/path
	 * When name starts with "-", it means absolute path
	 * Containers are separated by "-"
	 * Examples:
	 *  -container1-control => return $form["container1"]["control"]
	 *  containerx-control => return $currentContainer["containerx"]["control"]
	 * @param <type> $name
	 * @return <type>
	 */
	public static function getControl($name) {
		$name = (string)$name;
		if($name == "" || $name == "-") // todo: "" = form or container?
			throw new InvalidArgumentException("Control must be specified !");
		$names = explode("-", $name);
		if($names[0] == "") {
			$container = reset(self::$stack);
			unset($names[0]);
		} else
			$container = end(self::$stack);
		foreach($names as $name)
			$container = $container[$name];
		return $container;
	}

	protected static function applyModifiers(Html $element, array $modifiers) {
		foreach($modifiers as $key => $value)
			$element->$key($value);
	}

	protected static function fetchNameAndModifiers($content) {
		$latteMacros = self::getLatteMacros();
		$name = $latteMacros->fetchToken($content);
		$name = String::startsWith($name, '$') ? $name : "'$name'";
		$modifiers = $latteMacros->formatArray($content);
		$modifiers = $modifiers ?: "array()";
		return array($name, $modifiers);
	}

	// </editor-fold>

}