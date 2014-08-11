<?php
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

  public function setUp()
  {
    $capabilities = array(\WebDriverCapabilityType::BROWSER_NAME => 'firefox');
    $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
    $dev_url = 'http://fvdmug-automation.local:8083/';
    $this->webDriver->get($dev_url);
  }

  public function tearDown()
  {
    //$this->webDriver->close();
  }

  public function testIsAnon() {
    $logins = $this->webDriver->findElements(WebDriverBy::cssSelector('#edit-name'));
    $this->assertEquals(1, count($logins), 'Should have one login since we are anonymous');
    $passwords = $this->webDriver->findElements(WebDriverBy::cssSelector('#edit-pass'));
    $this->assertEquals(1, count($passwords), 'Should have one password since we are anonymous');
  }

  public function testAnonSimpleFormHasQuestions() {
    $this->goToForm();

    $this->formElementsExist();
  }

  public function testAnonSubmit() {
    $this->goToForm();

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

  /**
   * @param $expected_id
   */
  public function assertOneOf($expected_id)
  {
    $matches = $this->webDriver->findElements(WebDriverBy::id($expected_id));
    $this->assertEquals(1, count($matches), 'Should have one ' . $expected_id);
  }

  public function goToForm()
  {
    $link = $this->webDriver->findElement(WebDriverBy::linkText('Simple Anonymous'));
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

    $expected_muppet_count = 7;
    // This array starts at 1, not 0.
    for ($i = 1; $i <= $expected_muppet_count; $i++) {
      $muppet_id = "edit-submitted-favorite-muppet-$i";
      $this->assertOneOf($muppet_id);
    }
  }

}
 