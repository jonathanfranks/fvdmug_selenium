<?php
require_once('automation.php');
/**
 * Created by PhpStorm.
 * User: jfranks
 * Date: 7/6/14
 * Time: 9:52 AM
 */
class NewNodeCommentTest extends PHPUnit_Framework_TestCase
{

  /**
   * @var \RemoteWebDriver
   */
  protected $webDriver;

  public function setUp()
  {
    $capabilities = array(\WebDriverCapabilityType::BROWSER_NAME => 'chrome');
    $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
    $dev_url = 'http://fvdmug-automation.local:8083/';
    $this->webDriver->get($dev_url);
  }

  public function tearDown()
  {
    $this->webDriver->close();
  }

  public function testRegisterNewUserFormElements()
  {
    Automation::clickRegisterLink($this->webDriver);

    $expected_elements = array(
        'edit-name',
        'edit-mail',
        'edit-pass-pass1',
        'edit-pass-pass2',
        'edit-submit',
    );
    foreach ($expected_elements as $expected_element) {
      $this->assertOneOf($expected_element);
    }
  }

  // todo: Put this somewhere that I am not duplicating it
  /**
   * @param $expected_id
   */
  public function assertOneOf($expected_id)
  {
    $matches = $this->webDriver->findElements(WebDriverBy::id($expected_id));
    $this->assertEquals(1, count($matches), 'Should have one ' . $expected_id);
  }

  public function testRegisterNewUser()
  {
//    $now = time();
//    $this->clickRegisterLink();
//    $elements_values = array(
//        'edit-name' => $now,
//        'edit-mail' => "$now@example.com",
//        'edit-pass-pass1' => 'password',
//        'edit-pass-pass2'=> 'password',
//    );
//    foreach ($elements_values as $key => $value) {
//      $element = $this->webDriver->findElement(WebDriverBy::id($key));
//      $element->clear()->sendKeys($value);
//    }
//    $submit = $this->webDriver->findElement(WebDriverBy::id('edit-submit'));
//    $submit->click();

    $now = Automation::registerNewUser($this->webDriver);

    $messages = $this->webDriver->findElement(WebDriverBy::cssSelector('.messages'));
    $expected_text = 'Registration successful. You are now logged in.';
    $actual_text = $messages->getText();
    $this->assertContains($expected_text, $actual_text, 'Registration message');
  }

}
 