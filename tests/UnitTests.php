<?php

namespace nglasl\extensible\tests;

use nglasl\extensible\ExtensibleSearch;
use nglasl\extensible\ExtensibleSearchArchive;
use nglasl\extensible\ExtensibleSearchArchived;
use nglasl\extensible\ExtensibleSearchArchiveTask;
use nglasl\extensible\ExtensibleSearchPage;
use nglasl\extensible\ExtensibleSearchSuggestion;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Search\FulltextSearchable;

/**
 *	The extensible search specific unit testing.
 *	@author Nathan Glasl <nathan@symbiote.com.au>
 */

class UnitTests extends SapphireTest {

	protected $usesDatabase = true;

	protected $requireDefaultRecordsFrom = array(
		ExtensibleSearchPage::class
	);

	public static function setUpBeforeClass(): void {

		parent::setUpBeforeClass();

		// The full-text search needs to be enabled.

		$config = Config::modify();
		$config->merge(FulltextSearchable::class, 'searchable_classes', array(
			SiteTree::class
		));
		$config->merge(SiteTree::class, 'create_table_options', array(
			'MySQLDatabase' => 'ENGINE=MyISAM'
		));

		// This extension throws errors when it has already been applied.

		if(!SiteTree::has_extension(FulltextSearchable::class)) {
			SiteTree::add_extension(FulltextSearchable::class . "('Title', 'MenuTitle', 'Content', 'MetaDescription')");
		}
	}

	public function testSearchResults() {

		// The full-text search needs to be selected.

		$page = ExtensibleSearchPage::get()->first();
		$page->SearchEngine = 'Full-Text';
		$page->write();
		$controller = ModelAsController::controller_for($page);

		// This shouldn't find anything, since no searchable pages exist.

		$results = $controller->getSearchResults();
		$this->assertEquals($results->Count, 0);

		// Instantiate a searchable page.

		$searchable = SiteTree::create(
			array(
				'Title' => 'Test'
			)
		);
		$searchable->write();

		// This should now find the page.

		$results = $controller->getSearchResults();
		$this->assertEquals($results->Count, 1);
	}

	public function testAnalytics() {

		$page = ExtensibleSearchPage::get()->first();
		$controller = ModelAsController::controller_for($page);

		// This shouldn't find anything, since the query doesn't match the page.

		$data = array(
			'Search' => 'Nothing'
		);
		$results = $controller->getSearchResults($data);
		$this->assertEquals($results->Count, 0);

		// There should now be a single analytic, but no suggestions.

		$filter = array(
			'ExtensibleSearchPageID' => $page->ID
		);
		$this->assertEquals(ExtensibleSearch::get()->filter($filter)->count(), 1);
		$this->assertEquals(ExtensibleSearchSuggestion::get()->filter($filter)->count(), 0);

		// This should now find the page.

		$data = array(
			'Search' => 'Test'
		);
		$results = $controller->getSearchResults($data);
		$this->assertEquals($results->Count, 1);

		// There should now be two analytics, and a single suggestion.

		$this->assertEquals(ExtensibleSearch::get()->filter($filter)->count(), 2);
		$this->assertEquals(ExtensibleSearchSuggestion::get()->filter($filter)->count(), 1);

		// Trigger the task to archive past search analytics.

		singleton(ExtensibleSearchArchiveTask::class)->run(null);
		$this->assertEquals(ExtensibleSearch::get()->filter($filter)->count(), 0);
		$this->assertEquals(ExtensibleSearchSuggestion::get()->filter($filter)->count(), 1);

		// There should now be a single archive, containing two analytics.

		$archives = ExtensibleSearchArchive::get()->filter($filter);
		$this->assertEquals($archives->count(), 1);
		$this->assertEquals(ExtensibleSearchArchived::get()->filter(array(
			'ArchiveID' => $archives->first()->ID
		))->count(), 2);
	}

}
