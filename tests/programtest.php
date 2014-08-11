<?php
require_once('NaeycAutomation.php');

class ProgramTest extends PHPUnit_Framework_TestCase {
  const CARD_NUMBER = '370000000000002';

  /**
   * @var \RemoteWebDriver
   */
  protected $webDriver;
  protected $url;

  public function setUp()
  {
    $capabilities = array(\WebDriverCapabilityType::BROWSER_NAME => 'firefox');
    $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
    $dev_url = 'http://naeyc.local:8083/';
    $qa_url = 'http://qa.naeyc.drupal.breaktech.org/';
    $qa2_url = 'http://192.168.77.61/';
    $this->url = $dev_url;
    $this->webDriver->get($this->url);

    date_default_timezone_set('America/Chicago');

  }

  public function tearDown()
  {
    //$this->webDriver->close();
  }

  public function testOnce() {
    $this->doFullProgramLifecycle();
  }

  public function doFullProgramLifecycle() {
    $this->webDriver->get($this->url);

    $staff_uid = 'btstaff';
    $staff_pwd = 'a';

    $start_time = time();

    print $start_time . PHP_EOL;

    $email = $start_time . '@example.com';
    $pwd = 'a';
    $this->register($email, $pwd);
    $programID = $this->createNewProgram($start_time);
    $this->completeEnrollment();
    $this->completeApplication($this->url, $programID, $start_time);

    // Change the program's status to Applicant
    $this->logOut();
    $this->changeProgramStatus($staff_uid, $staff_pwd, $start_time, 'Applicant');
    $this->logOut();
    Automation::login($email, $pwd, $this->webDriver);

    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Applicant', $status->getText(), 'Status after staff has approved application');
    $this->completeCandidacy();

    // Change the program's status to Candidate
    $this->logOut();
    $this->changeProgramStatus($staff_uid, $staff_pwd, $start_time, 'Candidate');
    $this->logOut();

    Automation::login($email, $pwd, $this->webDriver);
    $this->program_editor_schedule_site_visit();
    $this->logOut();

    $this->staff_complete_site_visit($staff_uid, $pwd, $programID);

    $this->logOut();
    $this->changeProgramStatus($staff_uid, $staff_pwd, $start_time, 'Accredited');

    $this->logOut();
    Automation::login($email, $pwd, $this->webDriver);

    $ar1 = $this->webDriver->findElements(WebDriverBy::cssSelector('h4.accordion-title:nth-child(9)'));
    $this->assertEquals(0, count($ar1), 'AR1 h4 should not show up');

    $time_change = '-1 year';
    // Currently only working for the first AR because of the field collection repeating items' IDs
    for ($i = 0; $i < 1; $i++) {
      $this->logOut();
      // Log in with admin and change the program's vud to a year ago
      Automation::login('admin', 'break9tech9', $this->webDriver);
      $this->adminChangeProgramVUD($start_time, $time_change);
      $this->logOut();

      Automation::login($email, $pwd, $this->webDriver);
      // Now make sure AR1 shows up
      $this->annualReportCompleteProgramEvaluation();
      $this->annualReportCompleteRights();
      $this->annualReportCompletePayment();
      Automation::grabElementByCssSelector($this->webDriver, '#btn_annual > div:nth-child(1) > input:nth-child(1)')->click();
      $messages = Automation::grabElementByCssSelector($this->webDriver, '.messages')->getText();
      $this->assertContains('Thank you. Your Annual Report has been submitted.', $messages);
    }
    // Log out
    // Log in as admin
    // Change accreditation VUD to year-1
    // Log out
    // Log in as program editor
    // Assert that AR1 shows up
    // Assert that AR2 does not show up

  }

  public function logOut() {
    Automation::grabElementByCssSelector($this->webDriver, '.logout')->click();
  }

  public function changeProgramStatus($staff_uid, $staff_pwd, $start_time, $new_status)
  {
    Automation::login($staff_uid, $staff_pwd, $this->webDriver);
    Automation::grabElementByCssSelector($this->webDriver, '.pager-last > a:nth-child(1)')->click(); // Page to last page

    // And then all of this to wait for the list to refresh...
    $this->webDriver->wait(30, 1500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.pager-first'))
    );

    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-operation')));
    $select->selectByValue('action::naeyc_vbo_actions_change_status');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit--2'))->click();

    $checkboxes = $this->webDriver->findElements(WebDriverBy::xpath('//input[@type=\'checkbox\']'));
    $last_checkbox = end($checkboxes);
    $last_checkbox->click();

    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-status-dropdown')));
    $select->selectByValue($new_status);
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();

    $this->webDriver->wait(30, 1500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.messages'))
    );

    $this->assertContains("Program Webdriver Program $start_time changed from", Automation::grabElementByCssSelector($this->webDriver, '.messages')->getText());
  }

  private function completeEnrollment() {
    $this->showDashboard();

    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Pending Enrollment', $status->getText(), 'Status at beginning of enrollment phase');

    $step_text = Automation::grabElementByCssSelector($this->webDriver, '.accordion-title')->getText();
    $this->assertEquals('STEP 1: Enrollment', $step_text, 'Before enrollment is complete should just have the step and title');

    $classes = Automation::grabElementByCssSelector($this->webDriver, '.button')->getAttribute('class');
    $this->assertContains('disabled', $classes, 'Check that submit button is disabled');

    $step2_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.even > td:nth-child(1)')->getText();
    $this->assertEquals('2', $step2_text, 'Should have number instead of checkmark');

    $step_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.even > td:nth-child(3)')->getText();
    $this->assertEquals('Pending', $step_text, 'Should be Pending');

    $expectedFee = '$450';
    $this->payFee($expectedFee, 'tr.even > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)');
    $this->showDashboard();

    $classes = Automation::grabElementByCssSelector($this->webDriver, '.button')->getAttribute('class');
    $this->assertContains('disabled', $classes, 'Check that submit button is still disabled');

    $step3_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.completed:nth-child(2) > td:nth-child(3)')->getText();
    $this->assertEquals('Completed', $step3_text);

    $this->enrollmentRightsResponsibilities();
    $this->showDashboard();

    $classes = Automation::grabElementByCssSelector($this->webDriver, '.button')->getAttribute('class');
    $this->assertNotContains('form-button-disabled', $classes, 'Check that the submit button is now enabled');

    Automation::grabElementByCssSelector($this->webDriver, '.button')->click();
    $status = Automation::grabElementByCssSelector($this->webDriver, '.messages')->getText();
    $this->assertContains('Congratulations! Your program is enrolled effective', $status);

    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Pending Application', $status->getText(), 'Status at end of enrollment phase');

    $date_text = date('M d, Y', time());
    $step_text = Automation::grabElementByCssSelector($this->webDriver, '.accordion-title')->getText();
    $this->assertEquals("STEP 1: Enrollment Completed on: $date_text", $step_text, 'Before enrollment is complete should just have the step and title');


  }

  private function register($email, $password) {
    $this->webDriver->findElement(WebDriverBy::linkText('create an account'))->click();

    $first_name = 'Web';
    $last_name = 'Driver';
    $title = 'program owner';
    $area = '888';
    $phone = '588-2300';

    $this->webDriver->findElement(WebDriverBy::id('edit-profile-naeyc-main-profile-field-first-name-und-0-value'))->sendKeys($first_name);
    $this->webDriver->findElement(WebDriverBy::id('edit-profile-naeyc-main-profile-field-last-name-und-0-value'))->sendKeys($last_name);
    $this->webDriver->findElement(WebDriverBy::id('edit-profile-naeyc-main-profile-field-position-title-und-0-value'))->sendKeys($title);
    $this->webDriver->findElement(WebDriverBy::id('edit-profile-naeyc-main-profile-field-business-phone-number-und-0-field-area-code-und-0-value'))->sendKeys($area);
    $this->webDriver->findElement(WebDriverBy::id('edit-profile-naeyc-main-profile-field-business-phone-number-und-0-field-phone-number-und-0-value'))->sendKeys($phone);
    $this->webDriver->findElement(WebDriverBy::id('edit-mail'))->sendKeys($email);
    $this->webDriver->findElement(WebDriverBy::id('edit-pass-pass1'))->sendKeys($password);
    $this->webDriver->findElement(WebDriverBy::id('edit-pass-pass2'))->sendKeys($password);

    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function createNewProgram($time_stamp)
  {
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
    $this->webDriver->findElement(WebDriverBy::id("edit-title"))->sendKeys("Webdriver Program " . $time_stamp);
    $this->webDriver->findElement(WebDriverBy::id("edit-field-num-kindergarten-und-0-value"))->click();
    $this->webDriver->findElement(WebDriverBy::id("edit-field-num-kindergarten-und-0-value"))->sendKeys("1");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-num-teach-staff-und-0-value"))->click();
    $this->webDriver->wait(3, 500)->until(
      WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('edit-field-total-children-und-0-value--2'))
    );

    $this->webDriver->findElement(WebDriverBy::id("edit-field-num-teach-staff-und-0-value"))->sendKeys("1");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-primary-site-address-und-0-thoroughfare"))->sendKeys("1234 Webdriver Lane");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-primary-site-address-und-0-locality"))->sendKeys("Evanston");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-primary-site-address-und-0-administrative-area"))->sendKeys("label=Illinois");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-primary-site-address-und-0-postal-code"))->sendKeys("60201");
    $this->webDriver->findElement(WebDriverBy::id("edit-field-prog-characteristics-und-select-13"))->click();

    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();

    $program_id_element = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field:nth-child(2) > span:nth-child(1)');
    $program_id = $program_id_element->getText();
    $program_id = str_replace('(', '', $program_id);
    $program_id = str_replace(')', '', $program_id);
    return $program_id;
  }

  public function createSite($url, $programID)
  {
    $sites_profile_url = $url . 'program/' . $programID . '/sites';
    $this->webDriver->get($sites_profile_url);
    $this->webDriver->findElement(WebDriverBy::id('edit-button'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-title'))->sendKeys('Site One');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function createStaff($url, $programID)
  {
    $sites_profile_url = $url . 'program/' . $programID . '/staff';
    $this->webDriver->get($sites_profile_url);
    $this->webDriver->findElement(WebDriverBy::id('edit-button'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-first-name-und-0-value'))->sendKeys('First');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-last-name-und-0-value'))->sendKeys('Last');

    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-position-und')));
    $select->selectByValue('0');

    $this->webDriver->findElement(WebDriverBy::id('edit-field-employment-status-und-fulltime'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function createLicensure($start_time)
  {
    $this->webDriver->findElement(WebDriverBy::linkText('Legal & Licensure'))->click();
    $this->webDriver->wait(2, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::linkText('Licensing'))
    );
    $this->webDriver->findElement(WebDriverBy::linkText('Licensing'))->click();
    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-licensure-state-und')));
    $select->selectByValue('IL');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-licensure-agency-und-0-value'))->sendKeys('DCFS');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-license-number-und-0-value'))->sendKeys('12345');

    $this->webDriver->findElement(WebDriverBy::id('edit-field-license-expiration-und-0-value-datepicker-popup-0'))->sendKeys('Jul 23 2015');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-specialist-name-und-0-value'))->sendKeys('Mister WebDriver');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-corporate-structure-und-3'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();

    $status = Automation::grabElementByCssSelector($this->webDriver, '.messages')->getText();
    $this->assertContains("Licensing Legal & Licensure | Webdriver Program $start_time has been created.", $status);
  }

  public function payFee($expectedFee, $selector)
  {
    $this->showDashboard();

    Automation::grabElementByCssSelector($this->webDriver, $selector)->click();
    $fee = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field:nth-child(2) > div:nth-child(2)');
    $fee_text = $fee->getText();
    $this->assertContains($expectedFee, $fee_text);

    $opener = Automation::grabElementByCssSelector($this->webDriver, '#opener');
    $opener->click();

    $top_cell = Automation::grabElementByCssSelector($this->webDriver, 'tr.odd:nth-child(1) > td:nth-child(1)');
    // commented because some of them display that "until december..." message
    // Need logic to check for that
    //$this->assertEquals($expectedFee, $top_cell->getText(), 'Fee should match expected value');

    $this->webDriver->findElement(WebDriverBy::id('edit-customer-profile-billing-commerce-customer-address-und-0-name-line'))->sendKeys('Web Driver');
    $this->webDriver->findElement(WebDriverBy::id('edit-continue'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-commerce-payment-payment-details-credit-card-number'))->sendKeys(self::CARD_NUMBER);

    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-commerce-payment-payment-details-credit-card-exp-year')));
    $select->selectByValue('2018');

    $this->webDriver->findElement(WebDriverBy::id('edit-commerce-payment-payment-details-credit-card-code'))->sendKeys('1234');
    $this->webDriver->findElement(WebDriverBy::id('edit-continue'))->click();
//    $status = NaeycAutomation::grabElementByCssSelector($this->webDriver, '.messages'))->getText();
//    $this->assertContains('Your payment is successfully processed. Thank you! Order number:', $status);

    $url = $this->webDriver->getCurrentURL();
    $this->assertStringEndsWith('/checklists', $url);

  }

  public function showDashboard()
  {
    $this->webDriver->findElement(WebDriverBy::linkText('My Dashboard'))->click();
    $this->webDriver->wait(2, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::linkText('Accreditation Process'))
    );
    $this->webDriver->findElement(WebDriverBy::linkText('Accreditation Process'))->click();
  }

  public function enrollmentRightsResponsibilities()
  {
    $this->showDashboard();
    $step_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.odd:nth-child(3) > td:nth-child(3)')->getText();
    $this->assertEquals('Pending', $step_text);
    Automation::grabElementByCssSelector($this->webDriver, 'tr.odd:nth-child(3) > td:nth-child(4) > a:nth-child(1)')->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-read-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-verify-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-plan-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-text-signature-und-0-value'))->sendKeys('WD');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
    $step_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.completed:nth-child(3) > td:nth-child(3)')->getText();
    $this->assertEquals('Completed', $step_text);
  }

  public function completeApplication($url, $programID, $start_time)
  {
    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Pending Application', $status->getText(), 'Status at beginning of application phase');

    $this->createSite($url, $programID);
    $this->createStaff($url, $programID);
    $this->createLicensure($start_time);
    $this->applicationGroupProfiles($url);

    $expectedFee = '$225';
    $this->payFee($expectedFee, 'tr.odd:nth-child(7) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)');

    $this->showDashboard();

    $this->applicationRightsResponsibilities();

    // Should be back at dashboard. Complete the application!
    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('#btn_apply > div:nth-child(1) > input:nth-child(1)'))
    );
    Automation::grabElementByCssSelector($this->webDriver, '#btn_apply > div:nth-child(1) > input:nth-child(1)')->click();

    $this->assertEquals('Candidacy Due Date | NAEYC AMS', $this->webDriver->getTitle());

    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-candidacy-due-date-und-11')->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();

    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Pending Application Approval', $status->getText(), 'Status at end of application phase');
  }

  public function adminUpdateProgramStatus($url, $program_id, $new_status)
  {
    $this->webDriver->get($url . '/node/' . $program_id . '/edit');
    $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-program-status-und')));
    $select->selectByValue($new_status);
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function completeSelfAssessment()
  {
    Automation::grabElementByCssSelector($this->webDriver, '.checklist-candidacy > div:nth-child(2) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(2) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-question-1-und-0')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#node_psar_form_group_question_1 > div:nth-child(3) > span:nth-child(1) > input:nth-child(2)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-question-2-und-0')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#node_psar_form_group_question_2 > div:nth-child(3) > span:nth-child(1) > input:nth-child(2)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-question-3-und-0')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#node_psar_form_group_question_3 > div:nth-child(3) > span:nth-child(1) > input:nth-child(2)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-question-4-und-select-0')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-evidence1-und-0-value')->sendKeys('1234');
    Automation::grabElementByCssSelector($this->webDriver, '#node_psar_form_group_question_4 > div:nth-child(3) > span:nth-child(1) > input:nth-child(2)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-question-5-und-select-0')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-evidence2-und-0-value')->sendKeys('1234');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  /**
   * @param $staff_uid
   * @param $pwd
   * @param $program_id
   */
  public function staff_complete_site_visit($staff_uid, $pwd, $program_id)
  {
    date_default_timezone_set('America/Chicago');
    $now_text = date('M d Y', time());

    Automation::login($staff_uid, $pwd, $this->webDriver);

    $this->webDriver->findElement(WebDriverBy::linkText('Site Visits'))->click();
    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::linkText('View All'))
    );
    $this->webDriver->findElement(WebDriverBy::linkText('View All'))->click();

    $program_link = $this->webDriver->findElement(WebDriverBy::linkText($program_id));
    $visit_edit_link = $program_link->findElement(WebDriverBy::xpath('./parent::*/parent::*/td[7]/a'));
    $url = $visit_edit_link->getAttribute('href');
    $this->assertContains('destination=admin/naeyc/sitevisits', $url, 'Should contain a destination back to here');
    $visit_edit_link->click();

    // Yes. This looks like it does NOTHING.
    $opt1 = Automation::grabElementByCssSelector($this->webDriver, '#edit-field-exclusion-dates-und-1');
    $opt1->click();
    $opt0 = Automation::grabElementByCssSelector($this->webDriver, '#edit-field-exclusion-dates-und-0');
    $opt0->click();
    // But what it actually does is let WebDriver catch up to the DOM once the calendar javascript is finished.
    // Clicking the exclusion thing and then clicking back puts it in sync

    $from_picker = Automation::grabElementByCssSelector($this->webDriver, '#edit-field-site-visit-15-day-window-und-0-value-datepicker-popup-0');
    $to_picker = Automation::grabElementByCssSelector($this->webDriver, '#edit-field-site-visit-15-day-window-und-0-value2-datepicker-popup-0');
    $from_picker->sendKeys($now_text);
    $to_picker->sendKeys($now_text);

    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-communicated-15-day-window-und')->click();

    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-site-visit-date-und-0-value-datepicker-popup-0')->sendKeys($now_text);

    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-communicated-prior-day-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-site-visit-performed-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-visit-materials-sent-und')->click();

    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('edit-submit-limited'))
    );
    $this->webDriver->findElement(WebDriverBy::id('edit-submit-limited'))->click();
  }

  public function completeCandicacyRR()
  {
    Automation::grabElementByCssSelector($this->webDriver, '.checklist-candidacy > div:nth-child(2) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(7) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-read-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-verify-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-meet-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-checkbox-list-1-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-notify-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-accurate1-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-accurate2-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-accurate3-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-accurate4-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-accurate5-und')->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-text-signature-und-0-value'))->sendKeys('WD');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function program_editor_schedule_site_visit()
  {
    $this->webDriver->findElement(WebDriverBy::linkText('Site Visit & Schedules'))->click();
    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::linkText('Site Visit'))
    );
    $this->webDriver->findElement(WebDriverBy::linkText('Site Visit'))->click();

    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-exclusion-dates-und-0')->click();
    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('edit-submit-limited'))
    );
    $this->webDriver->findElement(WebDriverBy::id('edit-submit-limited'))->click();

    $actual_url = $this->webDriver->getCurrentURL();
    $expected_url = 'checklists';
    $this->assertContains($expected_url, $actual_url, 'Should take us back to /checklists');
  }

  public function completeCandidacy()
  {
    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Applicant', $status->getText(), 'Status at beginning of Candidacy phase');

    $this->completeSelfAssessment();

    $expectedFee = '$750';
    $this->payFee($expectedFee, '.checklist-candidacy > div:nth-child(2) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(8) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)');

    $this->completeCandicacyRR();

    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('input[value="Submit Candidacy"]'))
    );

    Automation::grabElementByCssSelector($this->webDriver, 'input[value="Submit Candidacy"]')->click();

    $status = Automation::grabElementByCssSelector($this->webDriver, 'div.views-field-field-overall-status');
    $this->assertEquals('Overall Status: Pending Candidacy', $status->getText(), 'Status at ending of Candidacy phase');
  }

  /**
   * @param $start_time
   *  The timestamp on the user ID we are modifying the program for
   * @param $time_change
   *  $time parameter passed to strtotime
   */
  public function adminChangeProgramVUD($start_time, $time_change)
  {
    $this->webDriver->get($this->url . '/admin/structure/entity-type/checklist/accredited');
    Automation::grabElementByCssSelector($this->webDriver, '.pager-last > a:nth-child(1)')->click(); // Page to last page
    // And then all of this to wait for the list to refresh...
    $this->webDriver->wait(3, 500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.pager-first'))
    );

    $link = $this->webDriver->findElement(WebDriverBy::linkText("Checklist Accredited | Webdriver Program $start_time"));
    $link->click();

    $edit_url = $this->webDriver->getCurrentURL() . '/edit';
    $this->webDriver->get($edit_url);

    $vud_box = Automation::grabElementByCssSelector($this->webDriver, '#edit-field-accreditation-valid-until-und-0-value-date');
    $vud = $vud_box->getAttribute('value');

    $vud_date = strtotime($vud);
    $minus1year = strtotime($time_change, $vud_date);
    $minus1year_text = date('M d Y', $minus1year);
    $vud_box->clear();
    $vud_box->sendKeys($minus1year_text);
    $submit = Automation::grabElementByCssSelector($this->webDriver, '#edit-submit');
    $submit->click();
  }

  public function annualReportCompleteProgramEvaluation()
  {
    $prog_eval_cont_imp_link = Automation::grabElementByCssSelector($this->webDriver, '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(7) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)');
    $prog_eval_cont_imp_link->click();


    $first_select_box = $this->webDriver->findElement(WebDriverBy::xpath('//*/select'));
    $id = $first_select_box->getAttribute('id');
    print $id;
    $id_array = explode('-', $id);
    $webform_ident = $id_array[3];

    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-$webform_ident-meets-standard-1")->click();

    $one_year_ago = strtotime('-1 year', time());
    $month = date('n', $one_year_ago);
    $day = intval(date('d', $one_year_ago));
    $year = date('Y', $one_year_ago);

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-last-program-evaluation-month"));
    $select->selectByValue($month);

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-last-program-evaluation-day"));
    $select->selectByValue($day);

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-last-program-evaluation-year"));
    $select->selectByValue($year);

    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-evidence-forms-surveys-select-1")->click();

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-question-4-when-month"));
    $select->selectByValue($month);

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-question-4-when-day"));
    $select->selectByValue($day);

    $select = new WebDriverSelect(Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-question-4-when-year"));
    $select->selectByValue($year);

    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-$webform_ident-criterion-$webform_ident-response-criterion-$webform_ident-question-4-how-select-1")->click();
    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-10f03-criterion-10f03-response-criterion-10f03-program-meets-standard-1")->click();
    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-10f03-criterion-10f03-response-criterion-10f03-two-program-improvement-goals")->sendKeys('1234');
    Automation::grabElementByCssSelector($this->webDriver, "#edit-submitted-criterion-10f03-criterion-10f03-response-criterion-10f03-professional-development-activity")->sendKeys('4321');
    Automation::grabElementByCssSelector($this->webDriver, '.webform-submit')->click();
  }

  public function annualReportCompleteRights()
  {
    $step_text = Automation::grabElementByCssSelector($this->webDriver, '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(8) > td:nth-child(3)')->getText();
    $this->assertEquals('Pending', $step_text);
    Automation::grabElementByCssSelector($this->webDriver, '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(8) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-read-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-verify-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-rights-eligible-und')->click();
    Automation::grabElementByCssSelector($this->webDriver, '#edit-field-text-signature-und-0-value')->clear()->sendKeys('WD');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
    $step_text = Automation::grabElementByCssSelector($this->webDriver, '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(8) > td:nth-child(3)')->getText();
    $this->assertEquals('Completed', $step_text);
  }

  public function annualReportCompletePayment()
  {
    $step_text = Automation::grabElementByCssSelector($this->webDriver, '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(9) > td:nth-child(3)')->getText();
    $this->assertEquals('Pending', $step_text);

    $this->payFee('$300', '.entity-field-collection-item > div:nth-child(1) > table:nth-child(2) > tbody:nth-child(2) > tr:nth-child(9) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)');

    $step_text = Automation::grabElementByCssSelector($this->webDriver, 'tr.completed:nth-child(9) > td:nth-child(3)')->getText();
    $this->assertEquals('Completed', $step_text);
  }

  /**
   * @param $url
   */
  public function applicationGroupProfiles($url)
  {
    $this->webDriver->get($url . 'program/groups');
    $this->webDriver->findElement(WebDriverBy::id('edit-button'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-group-name-und-0-value'))->sendKeys('Group 1');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-total-children-und-0-value'))->sendKeys('10');
    $this->webDriver->findElement(WebDriverBy::id('edit-field-children-age-category-und-3'))->click();
    $selectMSH = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-starthours-hours')));
    $selectMSH->selectByValue('6');
    $selectMSM = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-starthours-minutes')));
    $selectMSM->selectByValue('00');
    $selectMSA = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-starthours-ampm')));
    $selectMSA->selectByValue('am');

    $selectMEH = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-endhours-hours')));
    $selectMEH->selectByValue('6');
    $selectMEM = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-endhours-minutes')));
    $selectMEM->selectByValue('00');
    $selectMEA = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::id('edit-field-group-schedule-und-2-endhours-ampm')));
    $selectMEA->selectByValue('pm');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public function applicationRightsResponsibilities()
  {
    Automation::grabElementByCssSelector($this->webDriver, 'tr.even:nth-child(6) > td:nth-child(4) > a:nth-child(1) > span:nth-child(1)')->click();
    $this->assertEquals('Rights and Responsibilities | NAEYC AMS', $this->webDriver->getTitle());
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-read-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-plan-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-rights-verify-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-checkbox-list-1-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-checkbox-list-2-und'))->click();
    $this->webDriver->findElement(WebDriverBy::id('edit-field-text-signature-und-0-value'))->sendKeys('WD');
    $this->webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }
}
