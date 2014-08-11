<?php

/**
 * Created by PhpStorm.
 * User: jfranks
 * Date: 8/10/14
 * Time: 10:05 PM
 */
class DrupalSelenium_TestCase extends PHPUnit_Framework_TestCase
{
  protected static $webDriver;

  /**
   * @param mixed $expected - ID of expected element
   * Fails assertion if there is not exactly one of the specified element
   */
  public static function assertEquals($expected)
  {
    $matches = self::$webDriver->findElements(WebDriverBy::id($expected));
    self::assertEquals(1, count($matches), 'Should have one ' . $expected);
  }
} 