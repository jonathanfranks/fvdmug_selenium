<?php
require_once('Automation.php');
/**
 * Created by PhpStorm.
 * User: jfranks
 * Date: 7/6/14
 * Time: 9:52 AM
 */

class WebformTest extends PHPUnit_Framework_TestCase {

  /**
   * @var \RemoteWebDriver
   */
  protected $webDriver;
  protected $url;

  public function setUp()
  {
    $capabilities = array(\WebDriverCapabilityType::BROWSER_NAME => 'chrome');
    $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
    $dev_url = 'http://fvdmug-automation.local:8083/';
    $this->url = $dev_url;
    $this->webDriver->get($this->url);
  }

  public function tearDown()
  {
    $this->webDriver->close();
  }

  public function testIsAnon() {
    $logins = $this->webDriver->findElements(WebDriverBy::cssSelector('#edit-name'));
    $this->assertEquals(1, count($logins), 'Should have one login since we are anonymous');
    $passwords = $this->webDriver->findElements(WebDriverBy::cssSelector('#edit-pass'));
    $this->assertEquals(1, count($passwords), 'Should have one password since we are anonymous');
  }

  public function testAnonSimpleFormHasQuestions() {
    $this->goToForm('Simple Anonymous');

    $this->formElementsExist();
  }

  public function testAnonSubmit() {
    $this->goToForm('Simple Anonymous');

    $now = time();
    $ids_values = array(
      'edit-submitted-name' => "Anonymous $now",
      'edit-submitted-email' => "$now@example.com",
    );
    foreach ($ids_values as $key => $value) {
      $element = $this->webDriver->findElement(WebDriverBy::id($key));
      $element->clear();
      $element->sendKeys($value);
    }
    $muppet_radio = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-favorite-muppet-2'));
    $muppet_radio->click();

    $submit_button = $this->webDriver->findElement(WebDriverBy::id('edit-submit'));
    $submit_button->click();

    $conf = $this->webDriver->findElement(WebDriverBy::cssSelector('.webform-confirmation'));
    $expected_conf = 'Thank you, your submission has been received.';
    $actual_conf = $conf->getText();
    $this->assertEquals($expected_conf, $actual_conf, 'Confirmation message does not match');

    $go_back_links = $this->webDriver->findElements(WebDriverBy::linkText('Go back to the form'));
    $this->assertEquals(1, count($go_back_links), 'Should have Go back link');
    $go_back_links[0]->click();

    $this->formElementsExist();

  }

  function testDefaultValuesAnon() {
    $this->goToForm('Default Value Fields');
    $txt1 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-not-required'));
    $this->assertEquals('' ,$txt1->getText(), 'Should start empty');

    $txt2 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-default-value'));
    $this->assertEquals('Default', $txt2->getAttribute('value'), 'Should start Default');

    $txt3 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-default-your-name'));
    $this->assertEquals('', $txt3->getText(), 'Anon user should not have a name filled in');
  }

  function testDefaultValuesAuth() {
    $uname = Automation::registerNewUser($this->webDriver);

    $this->goToForm('Default Value Fields');
    $txt1 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-not-required'));
    $this->assertEquals('' ,$txt1->getText(), 'Should start empty');

    $txt2 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-default-value'));
    $this->assertEquals('Default', $txt2->getAttribute('value'), 'Should start Default');

    $txt3 = $this->webDriver->findElement(WebDriverBy::id('edit-submitted-default-your-name'));
    $this->assertEquals($uname, $txt3->getAttribute('value'), 'Auth user should have a name filled in');
  }

  function testDefaultValuesFields() {
    $this->goToForm('Default Value Fields');
    $form = $this->webDriver->findElement(WebDriverBy::cssSelector('.webform-client-form'));
    $fields = $form->findElements(WebDriverBy::tagName('input'));
    $input_count_modifier = 7; // There are 6 hidden fields plus the submit button
    $this->assertEquals(3 + $input_count_modifier, count($fields), 'Should have 3 fields');
  }

  /**
   * @param $expected_id
   */
  public function assertOneOf($expected_id)
  {
    $matches = $this->webDriver->findElements(WebDriverBy::id($expected_id));
    $this->assertEquals(1, count($matches), 'Should have one ' . $expected_id);
  }

  public function goToForm($link_text)
  {
    $this->webDriver->get($this->url); // Go Home first
    $link = $this->webDriver->findElement(WebDriverBy::linkText($link_text));
    $link->click();
  }

  public function formElementsExist()
  {
    $expected_ids = array(
        'edit-submitted-name',
        'edit-submitted-email',
        'edit-submitted-favorite-muppet',
    );
    foreach ($expected_ids as $expected_id) {
      $this->assertOneOf($expected_id);
    }

    $muppet_radios = $this->webDriver->findElements(WebDriverBy::name('submitted[favorite_muppet]'));
    $actual_muppet_count = count($muppet_radios);
    $expected_muppet_count = 7;

    $this->assertEquals($expected_muppet_count, $actual_muppet_count);

    // This array starts at 1, not 0.
    for ($i = 1; $i <= $expected_muppet_count; $i++) {
      $muppet_id = "edit-submitted-favorite-muppet-$i";
      $this->assertOneOf($muppet_id);
    }
  }

}
 