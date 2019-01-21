<?php

use Faker\Factory;
use Faker\Generator;
use Helper\WebDriverHelper;
use Step\Acceptance\Accounts;
use Step\Acceptance\DetailView;
use Step\Acceptance\EditView;
use Step\Acceptance\ListView;
use Step\Acceptance\UsersTester;

class UsersCest
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
        if (!$this->fakeData) {
            $this->fakeData = Factory::create();
        }

        $this->fakeDataSeed = rand(0, 2048);
        $this->fakeData->seed($this->fakeDataSeed);
    }


    /**
     * @param \AcceptanceTester $I
     * @param \Step\Acceptance\ListView $listView
     * @param \Step\Acceptance\EditView $editView
     * @param \Step\Acceptance\UsersTester $users
     * @param \Step\Acceptance\SideBar $sidebar
     * @param \Helper\WebDriverHelper $webDriverHelper
     *
     * As an administrator I want to create a user.
     */
    public function testScenarioCreateUser(
        \AcceptanceTester $I,
        \Step\Acceptance\ListView $listView,
        \Step\Acceptance\EditView $editView,
        \Step\Acceptance\UsersTester $users,
        \Step\Acceptance\SideBar $sidebar,
        \Helper\WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('Create a user');

        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        $this->fakeData->seed($this->fakeDataSeed);
        $userName = 'Test_'. $this->fakeData->name;

        // Navigate to users list-view
        $I->loginAsAdmin();
        $users->gotoUsers();
        $I->click('User Management');
        $listView->waitForListViewVisible();

        // Create user
        $sidebar->clickSideBarAction('Create');
        $editView->waitForEditViewVisible();
        $I->fillField('user_name', $userName);
        $I->fillField('last_name', $this->fakeData->name);
        $I->scrollTo('#Users0emailAddress0');
        $I->fillField('#Users0emailAddress0', $this->fakeData->email);
        $I->executeJS('window.scrollTo(0,0); return true;');
        $I->click('Password');
        $I->fillField('#new_password', 'Test');
        $I->fillField('#confirm_pwd', 'Test');
        $editView->clickSaveButton();

        // Logout and Login
        $users->logoutUser();
        $I->fillField('#user_name', $userName);
        $I->fillField('#username_password', 'Test');
        $I->click('Log In');
        $I->waitForElementNotVisible('#loginform', 120);
    }
    
    public function testEmailSettingsMailAccountAdd(AcceptanceTester $I, UsersTester $Users, WebDriverHelper $webDriverHelper)
    {
        $instanceUrl = $webDriverHelper->getInstanceURL();
        $I->amOnUrl($instanceUrl);
        $I->loginAsAdmin();
        $Users->gotoProfile();
        $I->see('User Profile', '.panel-heading');
        $I->click('Settings');
        $I->see('Mail Accounts');
        $I->click('Mail Accounts');
        $I->click('Add');
        $I->executeJS('javascript:SUGAR.email2.accounts.fillInboundGmailDefaults();'); // <-- instead of $I->click('Prefill Gmail™ Defaults');
        $I->fillField('ie_name', 'testuser_acc');
        $I->fillField('email_user', 'testuser_name');
        $I->fillField('email_password', 'testuser_pass');
        $I->click('Test Settings');
        $I->wait(10);
        $I->see('Connection completed successfully.');
    }

    public function testShowCollapsedSubpanelHint(
        AcceptanceTester $I,
        DetailView $DetailView,
        UsersTester $Users,
        ListView $listView,
        EditView $EditView,
        Accounts $accounts,
        WebDriverHelper $webDriverHelper
    ) {
        $I->wantTo('View the collapsed subpanel hints on Accounts');

        $I->amOnUrl(
            $webDriverHelper->getInstanceURL()
        );

        // Navigate to Users list-view
        $I->loginAsAdmin();

        $Users->gotoProfile();

        $I->see('User Profile', '.panel-heading');

        $I->click("Layout Options");
        $I->wait(5);
        $I->seeElement('input', ['name' => 'user_count_collapsed_subpanels']);
        $I->checkOption(['name' => 'user_count_collapsed_subpanels']);
        $EditView->clickSaveButton();
        $DetailView->waitForDetailViewVisible();

        // Create & Navigate to Accounts
        // @TODO - Need to include dummy data to utilise these tests efficiently
        $I->wantTo('Create an Account');

        // Navigate to accounts list-view
        $accounts->gotoAccounts();
        $listView->waitForListViewVisible();

        // Create account
        $this->fakeData->seed($this->fakeDataSeed);
        $accounts->createAccount('Test_'. $this->fakeData->company());

        // View the Subpanels Hint
        $I->see('Leads (0)', '//*[@id="subpanel_title_leads"]/div/div');

        // Delete account
        $DetailView->clickActionMenuItem('Delete');
        $DetailView->acceptPopup();
        $listView->waitForListViewVisible();

        // Reset the collapsed subpanels
        $Users->gotoProfile();
        $I->see('User Profile', '.panel-heading');
        $I->click("Layout Options");
        $I->seeElement('input', ['name' => 'user_count_collapsed_subpanels']);
        $I->uncheckOption(['name' => 'user_count_collapsed_subpanels']);
        $EditView->clickSaveButton();
        $DetailView->waitForDetailViewVisible();
    }
}
