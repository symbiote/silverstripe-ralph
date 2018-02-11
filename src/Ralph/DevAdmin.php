<?php

namespace SilbinaryWolf\Ralph;
use Controller;
use DebugView;
use Director;
use FieldList;
use GridField;
use Page;
use SiteTree;
use Form;
use Requirements;
use LeftAndMain;
use GridFieldConfig_RecordViewer;
use Debug;
use Ralph;
use FormAction;
use DropdownField;
use ClassInfo;
use GridFieldPageCount;
use GridFieldPaginator;
use TextField;
use ToggleCompositeField;
use Config;

require_once(dirname(__FILE__).'/../ss4_compat.php');

class DevAdmin extends LeftAndMain {
	private static $allowed_actions = array(
		'index',
		'Form',
		//'dofilter',
		//'clearfilter',
	);

	/**
	 * @config
	 * @var string $url_base
	 */
	private static $url_base = 'dev';

	/**
	 * @config
	 * @var string
	 */
	private static $url_segment = 'ralph';

	/**
	 * @config
	 * @var int
	 */
	private static $default_items_per_page = 12;

	public function init() {
		parent::init();
		Requirements::css(Ralph::MODULE_DIR . '/css/devadmin.css');
	}

	public function index($request) {
		$result = $this->renderWith('RalphDevAdmin');
		return $result;
	}

	/**
	 * @return Form
	 */
	public function Form() {
		$fields = new FieldList();

		$fields->push($classTypeField = DropdownField::create('ClassType', 'Class Type'));

		// Get list of classes
		$classes = ClassInfo::subclassesFor('DataObject');
		unset($classes['DataObject']);
		asort($classes);
		$classTypeField->setSource($classes);

		// Determine class type from request (or fallback to default)
		$classType = $this->getRequest()->requestVar('ClassType');
		if (!$classType) {
			if (class_exists('Page')) {
				$classType = 'Page';
			} else if (class_exists('SiteTree')) {
				$classType = 'SiteTree';
			}
		}
		$classTypeField->setValue($classType);

		// Add filter fields
		$searchFields = new FieldList();
		$scaffoldFields = singleton($classType)->scaffoldFormFields();
		$searchFields->push(TextField::create('Field_ID', 'ID'));
		foreach ($scaffoldFields->dataFields() as $fieldName => $field) {
			$relObject = singleton($classType)->relObject($fieldName);
			if (!$relObject) {
				continue;
			}
			$field = $relObject->scaffoldSearchField();
			if (!$field) {
				continue;
			}
			$field->setName('Field_'.$fieldName);
			$searchFields->push($field);
		}
		$fields->push(ToggleCompositeField::create('Search', 'Search', $searchFields)->addExtraClass('ralph-search-fields'));
		if (!$this->HasClearedFilter()) {
			foreach ($this->getRequest()->requestVars() as $key => $value) {
				$field = $fields->dataFieldByName($key);
				if (!$field) {
					continue;
				}
				$field->setValue($value);
			}
		}

		if ($classType && class_exists($classType)) {
			$fields->push($this->getGridField($classType));
		}

		$actions = new FieldList();
		$actions->push(FormAction::create('index', 'Filter'));
		$form = Form::create($this, __FUNCTION__, $fields, $actions);
		$form->setTemplate('RalphDevAdmin_Form');
		return $form;
	}

	/**
	 * @return GridField
	 */
	protected function getGridField($classType) {
		// Filter by request
		$filters = array();
		if (!$this->HasClearedFilter()) {
			foreach ($this->getRequest()->requestVars() as $key => $value) {
				if (strpos($key, 'Field_') !== 0) {
					continue;
				}
				if ($value === '') {
					continue;	
				}
				$column = preg_replace('/^Field_/', '', $key);
				$filters[$column] = $value;
			}
		}
		$list = $classType::get();
		$list = $list->filter($filters);

		$gridField = GridField::create('RalphGridFieldItems', 'Items', $list, $config = GridFieldConfig_RecordViewer::create());

		$config->removeComponentsByType('GridFieldViewButton');
		$config->removeComponentsByType('GridFieldPaginator');
		$columnComponent = $config->getComponentByType('GridFieldDataColumns');
		$config->addComponent($pagination = new GridFieldPaginator($this->config()->default_items_per_page));
		$columns = array(
			'ID' => 'ID',
			'LastEdited' => 'LastEdited',
			'Created' => 'Created'
		);
		foreach (singleton($classType)->db() as $columnName => $type) {
			$columns[$columnName] = $columnName;
		}
		foreach (singleton($classType)->hasOne() as $columnName => $type) {
			$columnName = $columnName.'ID';
			$columns[$columnName] = $columnName;
		}
		$columnComponent->setDisplayFields($columns);
		return $gridField;
	}

	public function renderHeader() {
		ob_start();
		$renderer = new DebugView;
		$renderer->writeHeader();
		$renderer->writeInfo("SilverStripe Development Tools: Ralph", Director::absoluteBaseURL());
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	public function renderFooter() {
		ob_start();
		$renderer = new DebugView;
		$renderer->writeFooter();
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	public function Link($action = null) {
		$link = Controller::join_links(BASE_URL, parent::Link($action));
		return $link;
	}

	/**
	 * @return boolean
	 */
	protected function HasClearedFilter() {
		return $this->getRequest()->requestVar('action_clearfilter');
	}
}