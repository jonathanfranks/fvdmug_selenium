<?php
/**
 * Created by PhpStorm.
 * User: jfranks
 * Date: 7/18/14
 * Time: 2:43 PM
 */

class Automation {
  public static function login($email, $password, $webDriver) {
    $webDriver->findElement(WebDriverBy::id('edit-name'))->sendKeys($email);
    $webDriver->findElement(WebDriverBy::id('edit-pass'))->sendKeys($password);
    $webDriver->findElement(WebDriverBy::id('edit-submit'))->click();
  }

  public static function grabElementByCssSelector(RemoteWebDriver $webDriver, $cssSelector) {
    $webDriver->wait(10, 1500)->until(
        WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($cssSelector))
    );
    $element = $webDriver->findElement(WebDriverBy::cssSelector($cssSelector));
    return $element;
  }


} 