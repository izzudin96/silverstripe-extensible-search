<?php

namespace nglasl\extensible;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\View\Requirements;

/**
 *	Details of a user search generated suggestion.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class ExtensibleSearchSuggestion extends DataObject implements PermissionProvider {

	private static $table_name = 'ExtensibleSearchSuggestion';

	/**
	 *	Store the frequency to make search suggestion relevance more efficient.
	 */

	private static $db = array(
		'Term' => 'Varchar(255)',
		'Frequency' => 'Int',
		'Approved' => 'Boolean'
	);

	private static $has_one = array(
		'ExtensibleSearchPage' => ExtensibleSearchPage::class
	);

	private static $default_sort = 'Frequency DESC, Term ASC';

	private static $summary_fields = array(
		'Term',
		'FrequencySummary',
		'FrequencyPercentage',
		'ApprovedField'
	);

	private static $indexes = array(
		'Approved' => true,
		'SearchPageID_Approved' => array('type' => 'index', 'value' => '"ExtensibleSearchPageID","Approved"'),
	);

	/**
	 *	Allow the ability to disable search suggestions.
	 */

	private static $enable_suggestions = true;

	/**
	 *	Allow the ability to automatically approve user search generated suggestions.
	 */

	private static $automatic_approval = false;

	/**
	 *	Create a unique permission for management of search suggestions.
	 */

	public function providePermissions() {

		return array(
			'EXTENSIBLE_SEARCH_SUGGESTIONS' => array(
				'category' => _t('EXTENSIBLE_SEARCH.EXTENSIBLE_SEARCH', 'Extensible search'),
				'name' => _t('EXTENSIBLE_SEARCH.MANAGE_SEARCH_SUGGESTIONS', 'Manage search suggestions'),
				'help' => 'Allow management of user search generated suggestions.'
			)
		);
	}

	public function canView($member = null) {

		return true;
	}

	public function canEdit($member = null) {

		return $this->canCreate($member);
	}

	public function canCreate($member = null, $context = array()) {

		return Permission::checkMember($member, 'EXTENSIBLE_SEARCH_SUGGESTIONS');
	}

	public function canDelete($member = null) {

		return Permission::checkMember($member, 'EXTENSIBLE_SEARCH_SUGGESTIONS');
	}

	/**
	 *	Retrieve the search suggestion title.
	 *
	 *	@return string
	 */

	public function getTitle() {

		return $this->Term;
	}

	public function getCMSFields() {

		$fields = parent::getCMSFields();
		$fields->removeByName('ExtensibleSearchPageID');
		$fields->dataFieldByName('Approved')->setTitle(_t('EXTENSIBLE_SEARCH.APPROVED?', 'Approved?'));

		// Make sure the search suggestions and frequency are read only.

		if($this->Term) {
			$fields->makeFieldReadonly('Term');
		}
		$fields->removeByName('Frequency');

		// Allow extension customisation.

		$this->extend('updateExtensibleSearchSuggestionCMSFields', $fields);
		return $fields;
	}

	/**
	 *	Confirm that the current search suggestion is valid.
	 */

	public function validate() {

		$result = parent::validate();

		// Confirm that the current search suggestion matches the minimum autocomplete length and doesn't already exist.

		if($result->isValid() && (strlen($this->Term) < 3)) {
			$result->addError('Minimum autocomplete length required!');
		}
		else if($result->isValid() && ExtensibleSearchSuggestion::get_one(ExtensibleSearchSuggestion::class, array(
			'ID != ?' => (int)$this->ID,
			'Term = ?' => $this->Term,
			'ExtensibleSearchPageID = ?' => $this->ExtensibleSearchPageID
		))) {
			$result->addError('Suggestion already exists!');
		}

		// Allow extension customisation.

		$this->extend('validateExtensibleSearchSuggestion', $result);
		return $result;
	}

	public function fieldLabels($includerelations = true) {

		return array(
			'Term' => _t('EXTENSIBLE_SEARCH.SEARCH_TERM', 'Search Term'),
			'FrequencySummary' => _t('EXTENSIBLE_SEARCH.ANALYTIC_FREQUENCY', 'Analytic Frequency'),
			'FrequencyPercentage' => _t('EXTENSIBLE_SEARCH.ANALYTIC_FREQUENCY_%', 'Analytic Frequency %'),
			'ApprovedField' => _t('EXTENSIBLE_SEARCH.APPROVED?', 'Approved?')
		);
	}

	/**
	 *	Retrieve the frequency for display purposes.
	 *
	 *	@return string
	 */

	public function getFrequencySummary() {

		return $this->Frequency ? $this->Frequency : '-';
	}

	/**
	 *	Retrieve the frequency percentage.
	 *
	 *	@return string
	 */

	public function getFrequencyPercentage() {

		$history = ExtensibleSearch::get()->filter('ExtensibleSearchPageID', $this->ExtensibleSearchPageID);
		return $this->Frequency ? sprintf('%.2f %%', ($this->Frequency / $history->count()) * 100) : '-';
	}

	/**
	 *	Retrieve the approved field for update purposes.
	 *
	 *	@return string
	 */

	public function getApprovedField() {

		$approved = CheckboxField::create(
			'Approved',
			'',
			$this->Approved
		)->addExtraClass('approved');

		// Restrict this field appropriately.

		$user = Member::currentUserID();
		if(!Permission::checkMember($user, 'EXTENSIBLE_SEARCH_SUGGESTIONS')) {
			$approved->setAttribute('disabled', 'true');
		}
		return $approved;
	}

}
