<?php

use Faker\Generator;

class PersonModuleCest
{

    /**
     * @var Generator $fakeData
     */
    protected $fakeData;

    /**
     * @var integer $fakeDataSeed
     */
    protected $fakeDataSeed;

    /**
     * @param AcceptanceTester $I
     */
    public function _before(AcceptanceTester $I)
    {
        if(!$this->fakeData) {
            $this->fakeData = Faker\Factory::create();
            $this->fakeDataSeed = rand(0, 2048);
        }
        $this->fakeData->seed($this->fakeDataSeed);
    }

    /**
     * @param AcceptanceTester $I
     */
    public function _after(AcceptanceTester $I)
    {
    }

    // Tests
    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\ModuleBuilder $moduleBuilder
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As an administrator I want to create and deploy a person module so that I can test
     * that the person functionality is working. Given that I have already created a module I expect to deploy
     * the module before testing.
     */
    public function testScenarioCreatePersonModule(
       \AcceptanceTester $I,
       \Step\Acceptance\ModuleBuilder $moduleBuilder,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Create a person module for testing');

        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        $moduleBuilder->createModule(
            \Page\PersonModule::$PACKAGE_NAME,
            \Page\PersonModule::$NAME,
            \SuiteCRM\Enumerator\SugarObjectType::person
        );
    }

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to view my person test module so that I can see if it has been
     * deployed correctly.
     */
    public function testScenarioViewPersonTestModule(
        \AcceptanceTester $I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('View Person Test Module');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Navigate to module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);

        $listView->waitForListViewVisible();
    }

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Step\Acceptance\DetailView $detailView
     * @param \Step\Acceptance\EditView $editView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to create a record with my person test module so that I can test
     * the standard fields.
     */
    public function testScenarioCreateRecord(
        \AcceptanceTester $I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Step\Acceptance\DetailView $detailView,
        \Step\Acceptance\EditView $editView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Create Person Test Module Record');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Go to Person Test Module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);
        $listView->waitForListViewVisible();

        // Select create Person Test Module form the current menu
        $navigationBar->clickCurrentMenuItem('Create ' . \Page\PersonModule::$NAME);

        // Create a record
        $this->fakeData->seed($this->fakeDataSeed);
        $editView->selectOption('#salutation', $this->fakeData->title);
        $this->fakeData->seed($this->fakeDataSeed);
        $editView->fillField('#first_name', $this->fakeData->firstName);
        $this->fakeData->seed($this->fakeDataSeed);
        $editView->fillField('#last_name', $this->fakeData->lastName);
        $editView->fillField('#title', $this->fakeData->jobTitle);
        $editView->fillField('#department', $this->fakeData->tld);
        $editView->fillField('#department', $this->fakeData->tld);

        $editView->fillField('#phone_work', $this->fakeData->phoneNumber);
        $editView->fillField('#phone_mobile', $this->fakeData->phoneNumber);
        $editView->fillField('#phone_home', $this->fakeData->phoneNumber);
        $editView->fillField('#phone_other', $this->fakeData->phoneNumber);
        $editView->fillField('#phone_fax', $this->fakeData->phoneNumber);

        $editView->fillField('#description', $this->fakeData->paragraph);

        $editView->fillField('#Test_'.\Page\PersonModule::$NAME.'0emailAddress0', $this->fakeData->companyEmail);

        $editView->fillField('#primary_address_street', $this->fakeData->streetAddress);
        $editView->fillField('#primary_address_city', $this->fakeData->city);
        $editView->fillField('#primary_address_state', $this->fakeData->randomAscii);
        $editView->fillField('#primary_address_postalcode', $this->fakeData->postcode);
        $editView->fillField('#primary_address_country', $this->fakeData->country);
        $editView->checkOption('#alt_checkbox');

        $editView->clickSaveButton();

        $detailView->waitForDetailViewVisible();
    }

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to view the record by selecting it in the list view
     */
    public function testScenarioViewRecordFromListView(
        \AcceptanceTester $I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Select Record from list view');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Go to Person Test Module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);
        $listView->waitForListViewVisible();

        $listView->clickFilterButton();
        $listView->click('Quick Filter');
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->title;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->firstName;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name .= $this->fakeData->lastName;
        $listView->fillField('#search_name_basic', $name);
        $listView->click('Search', '.submitButtons');
        $listView->wait(1);
        $this->fakeData->seed($this->fakeDataSeed);
        $listView->clickNameLink($name);
    }

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Step\Acceptance\DetailView $detailView
     * @param \Step\Acceptance\EditView $editView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to edit the record by selecting it in the detail view
     */
    public function testScenarioEditRecordFromDetailView(
        \AcceptanceTester$I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Step\Acceptance\DetailView $detailView,
        \Step\Acceptance\EditView $editView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Edit Person Test Module Record from detail view');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Go to Person Test Module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);
        $listView->waitForListViewVisible();

        // Select record from list view
        $listView->clickFilterButton();
        $listView->click('Quick Filter');
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->title;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->firstName;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name .= $this->fakeData->lastName;
        $listView->fillField('#search_name_basic', $name);
        $listView->click('Search', '.submitButtons');
        $listView->wait(1);
        $this->fakeData->seed($this->fakeDataSeed);
        $listView->clickNameLink($name);

        // Edit Record
        $detailView->clickActionMenuItem('Edit');

        // Save record
        $editView->click('Save');

        $detailView->waitForDetailViewVisible();
    }

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Step\Acceptance\DetailView $detailView
     * @param \Step\Acceptance\EditView $editView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to duplicate the record
     */
    public function testScenarioDuplicateRecordFromDetailView(
        \AcceptanceTester $I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Step\Acceptance\DetailView $detailView,
        \Step\Acceptance\EditView $editView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Duplicate Person Test Module Record from detail view');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Go to Person Test Module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);
        $listView->waitForListViewVisible();

        // Select record from list view
        $listView->clickFilterButton();
        $listView->click('Quick Filter');
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->title;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->firstName;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name .= $this->fakeData->lastName;
        $listView->fillField('#search_name_basic', $name);
        $listView->click('Search', '.submitButtons');
        $listView->wait(1);
        $this->fakeData->seed($this->fakeDataSeed);
        $listView->clickNameLink($name);

        // Edit Record
        $detailView->clickActionMenuItem('Duplicate');

        $editView->fillField('#first_name', $this->fakeData->firstName. ' '. $this->fakeData->lastName);
        $editView->fillField('#last_name', $this->fakeData->firstName. ' '. $this->fakeData->lastName);

        // Save record
        $editView->click('Save');

        $detailView->waitForDetailViewVisible();
        $detailView->clickActionMenuItem('Delete');
        $detailView->acceptPopup();

        $listView->waitForListViewVisible();
    }

    // TODO: TASK: UNDEFINED - Add test for create from vcard

    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\NavigationBar $navigationBar
     * @param \Step\Acceptance\ListView $listView
     * @param \Step\Acceptance\DetailView $detailView
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As administrative user I want to delete the record by selecting it in the detail view
     */
    public function testScenarioDeleteRecordFromDetailView(
        \AcceptanceTester $I,
        \Step\Acceptance\NavigationBar $navigationBar,
        \Step\Acceptance\ListView $listView,
        \Step\Acceptance\DetailView $detailView,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Delete Person Test Module Record from detail view');
        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $I->loginAsAdmin();

        // Go to Person Test Module
        $navigationBar->clickAllMenuItem(\Page\PersonModule::$NAME);
        $listView->waitForListViewVisible();

        // Select record from list view
        $listView->clickFilterButton();
        $listView->click('Quick Filter');
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->title;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name = $this->fakeData->firstName;
        $name .= ' ';
        $this->fakeData->seed($this->fakeDataSeed);
        $name .= $this->fakeData->lastName;
        $listView->fillField('#search_name_basic', $name);
        $listView->click('Search', '.submitButtons');
        $listView->wait(1);
        $this->fakeData->seed($this->fakeDataSeed);
        $listView->clickNameLink($name);

        // Delete Record
        $detailView->clickActionMenuItem('Delete');
        $detailView->acceptPopup();

        $listView->waitForListViewVisible();
    }
}