<?php

/**
 * Created by PhpStorm.
 * User: jfranks
 * Date: 7/18/14
 * Time: 2:43 PM
 */
class Automation
{
  public static function login($email, $password, $webDriver)
  {
    $webDriver->findElement(WebDriverBy::id('edit-name'))->sendKeys($email);
    $webDriver->findElement(WebDriverBy::id('edit-pass'))->sendKeys($password);
    $webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public static function grabElementByCssSelector(RemoteWebDriver $webDriver, $cssSelector)
  {
    $webDriver->wait(10, 1500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($cssSelector))
    );
    $element = $webDriver->findElement(WebDriverBy::cssSelector($cssSelector));
    return $element;
  }

  public static function clickRegisterLink($webDriver)
  {
    $register_link = $webDriver->findElement(WebDriverBy::linkText('Create new account'));
    $register_link->click();
  }


  public static function registerNewUser($webDriver)
  {
    $now = time();
    self::clickRegisterLink($webDriver);
    $elements_values = array(
        'edit-name' => $now,
        'edit-mail' => "$now@example.com",
        'edit-pass-pass1' => 'password',
        'edit-pass-pass2' => 'password',
    );
    foreach ($elements_values as $key => $value) {
      $element = $webDriver->findElement(WebDriverBy::id($key));
      $element->clear()->sendKeys($value);
    }
    $submit = $webDriver->findElement(WebDriverBy::id('edit-submit'));
    $submit->click();
    return $now;
  }

}