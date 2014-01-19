<?php

namespace Behat\Mink\Driver;

use Behat\SahiClient\Client;
use Behat\SahiClient\Exception\ConnectionException;

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Sahi (JS) driver.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class SahiDriver extends CoreDriver
{
    private $started = false;
    private $browserName;
    private $client;

    /**
     * Initializes Sahi driver.
     *
     * @param string $browserName browser to start (firefox, safari, ie, etc...)
     * @param Client $client      Sahi client instance
     */
    public function __construct($browserName, Client $client = null)
    {
        if (null === $client) {
            $client = new Client();
        }

        $this->client      = $client;
        $this->browserName = $browserName;
    }

    /**
     * Returns Sahi client instance.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Starts driver.
     */
    public function start()
    {
        $this->client->start($this->browserName);
        $this->started = true;
    }

    /**
     * Checks whether driver is started.
     *
     * @return Boolean
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Stops driver.
     */
    public function stop()
    {
        $this->client->stop();
        $this->started = false;
    }

    /**
     * Resets driver.
     */
    public function reset()
    {
        $js = <<<JS
(function(){
    var path,
        cookies = document.cookie.split('; ');

    for (var i = 0; i < cookies.length && cookies[i]; i++) {
        path = location.pathname;

        do {
            document.cookie = cookies[i] + '; path=' + path + '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
            path = path.replace(/.$/, '');
        } while (path);
    }
})()
JS;

        try {
            $this->executeScript($js);
        } catch (\Exception $e) {
            // ignore error
        }
    }

    /**
     * Visit specified URL.
     *
     * @param string $url url of the page
     */
    public function visit($url)
    {
        $this->client->navigateTo($url, true);
    }

    /**
     * Returns current URL address.
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        return $this->evaluateScript('document.URL');
    }

    /**
     * Reloads current page.
     */
    public function reload()
    {
        $this->visit($this->getCurrentUrl());
    }

    /**
     * Moves browser forward 1 page.
     */
    public function forward()
    {
        $this->executeScript('history.forward()');
    }

    /**
     * Moves browser backward 1 page.
     */
    public function back()
    {
        $this->executeScript('history.back()');
    }

    /**
     * Sets cookie.
     *
     * @param string $name
     * @param string $value
     */
    public function setCookie($name, $value = null)
    {
        if (null === $value) {
            $this->deleteCookie($name);
        } else {
            $value = str_replace('"', '\\"', $value);
            $this->executeScript(sprintf('_sahi._createCookie("%s", "%s")', $name, $value));
        }
    }

    /**
     * Deletes a cookie by name.
     *
     * @param string $name Cookie name.
     */
    protected function deleteCookie($name)
    {
        $nameEscaped = json_encode($name);

        $js = <<<JS
(function(){
    var path = location.pathname;

    do {
        document.cookie = {$nameEscaped} + '=; path=' + path + '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        path = path.replace(/.$/, '');
    } while (path);
})()
JS;

        try {
            $this->executeScript($js);
        } catch (\Exception $e) {
            // ignore error
        }
    }

    /**
     * Returns cookie by name.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getCookie($name)
    {
        try {
            $cookieValue = $this->evaluateScript(sprintf('_sahi._cookie("%s")', $name));

            return null === $cookieValue ? null : urldecode($cookieValue);
        } catch (ConnectionException $e) {
            // ignore error
        }

        return null;
    }

    /**
     * Returns last response content.
     *
     * @return string
     */
    public function getContent()
    {
        $html = $this->evaluateScript('document.getElementsByTagName("html")[0].innerHTML');
        $html = $this->removeSahiInjectionFromText($html);

        return "<html>\n$html\n</html>";
    }

    /**
     * Finds elements with specified XPath query.
     *
     * @param string $xpath
     *
     * @return array array of NodeElements
     */
    public function find($xpath)
    {
        $jsXpath = $this->prepareXPath($xpath);
        $function = <<<JS
(function(){
    var count = 0;
    while (_sahi._byXPath("({$jsXpath})["+(count+1)+"]")) count++;
    return count;
})()
JS;

        $count = intval($this->evaluateScript($function));
        $elements = array();
        for ($i = 0; $i < $count; $i++) {
            $elements[] = sprintf('(%s)[%d]', $xpath, $i + 1);
        }

        return $elements;
    }

    /**
     * Returns element's tag name by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getTagName($xpath)
    {
        return strtolower($this->client->findByXPath($this->prepareXPath($xpath))->getName());
    }

    /**
     * Returns element's text by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getText($xpath)
    {
        return $this->removeSahiInjectionFromText(
            $this->client->findByXPath($this->prepareXPath($xpath))->getText()
        );
    }

    /**
     * Returns element's html by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getHtml($xpath)
    {
        return $this->client->findByXPath($this->prepareXPath($xpath))->getHTML();
    }

    /**
     * Returns element's attribute by it's XPath query.
     *
     * @param string $xpath
     * @param string $name
     *
     * @return mixed
     */
    public function getAttribute($xpath, $name)
    {
        return $this->client->findByXPath($this->prepareXPath($xpath))->getAttr($name);
    }

    /**
     * Returns element's value by it's XPath query.
     *
     * @param string $xpath
     *
     * @return mixed
     */
    public function getValue($xpath)
    {
        $xpathEscaped = $this->prepareXPath($xpath);

        $tag   = $this->getTagName($xpath);
        $type  = $this->getAttribute($xpath, 'type');
        $value = null;

        if ('radio' === $type) {
            $name = $this->getAttribute($xpath, 'name');

            if (null !== $name) {
                $function = <<<JS
(function(){
    for (var i = 0; i < document.forms.length; i++) {
        if (document.forms[i].elements['{$name}']) {
            var form  = document.forms[i];
            var elements = form.elements['{$name}'];
            var value = elements[0].value;
            for (var f = 0; f < elements.length; f++) {
                var item = elements[f];
                if (item.checked) {
                    return item.value;
                }
            }
            return value;
        }
    }
    return null;
})()
JS;

                return $this->evaluateScript($function);
            }
        } elseif ('checkbox' === $type) {
            return $this->isChecked($xpath);
        } elseif ('select' === $tag && 'multiple' === $this->getAttribute($xpath, 'multiple')) {
            $function = <<<JS
(function(){
    var options = [],
        node = _sahi._byXPath("({$xpathEscaped})[1]");

        for (var i = 0; i < node.options.length; i++) {
            if (node.options[ i ].selected) {
                options.push(node.options[ i ].value);
            }
        }

    return options.join(",");
})()
JS;

            $value = $this->evaluateScript($function);

            if ('' === $value || false === $value) {
                return array();
            } else {
                return explode(',', $value);
            }
        }

        return $this->client->findByXPath($xpathEscaped)->getValue();
    }

    /**
     * Sets element's value by it's XPath query.
     *
     * @param string $xpath
     * @param string $value
     */
    public function setValue($xpath, $value)
    {
        $type = $this->getAttribute($xpath, 'type');

        if ('radio' === $type) {
            $this->selectRadioOption($xpath, $value);
        } elseif ('checkbox' === $type) {
            if ((Boolean) $value) {
                $this->check($xpath);
            } else {
                $this->uncheck($xpath);
            }
        } else {
            $this->client->findByXPath($this->prepareXPath($xpath))->setValue($value);
        }
    }

    /**
     * Checks checkbox by it's XPath query.
     *
     * @param string $xpath
     */
    public function check($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->check();
    }

    /**
     * Unchecks checkbox by it's XPath query.
     *
     * @param string $xpath
     */
    public function uncheck($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->uncheck();
    }

    /**
     * Checks whether checkbox checked located by it's XPath query.
     *
     * @param string $xpath
     *
     * @return Boolean
     */
    public function isChecked($xpath)
    {
        return $this->client->findByXPath($this->prepareXPath($xpath))->isChecked();
    }

    /**
     * Selects option from select field located by it's XPath query.
     *
     * @param string  $xpath
     * @param string  $value
     * @param Boolean $multiple
     */
    public function selectOption($xpath, $value, $multiple = false)
    {
        $type = $this->getAttribute($xpath, 'type');

        if ('radio' === $type) {
            $this->selectRadioOption($xpath, $value);
        } else {
            $this->client->findByXPath($this->prepareXPath($xpath))->choose($value, $multiple);
        }
    }

    /**
     * Checks whether select option, located by it's XPath query, is selected.
     *
     * @param string $xpath
     *
     * @return Boolean
     */
    public function isSelected($xpath)
    {
        return $this->client->findByXPath($this->prepareXPath($xpath))->isSelected();
    }

    /**
     * Clicks button or link located by it's XPath query.
     *
     * @param string $xpath
     */
    public function click($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->click();
    }

    /**
     * Double-clicks button or link located by it's XPath query.
     *
     * @param string $xpath
     */
    public function doubleClick($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->doubleClick();
    }

    /**
     * Right-clicks button or link located by it's XPath query.
     *
     * @param string $xpath
     */
    public function rightClick($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->rightClick();
    }

    /**
     * Attaches file path to file field located by it's XPath query.
     *
     * @param string $xpath
     * @param string $path
     */
    public function attachFile($xpath, $path)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->setFile($path);
    }

    /**
     * Checks whether element visible located by it's XPath query.
     *
     * @param string $xpath
     *
     * @return Boolean
     */
    public function isVisible($xpath)
    {
        return $this->client->findByXPath($this->prepareXPath($xpath))->isVisible();
    }

    /**
     * Simulates a mouse over on the element.
     *
     * @param string $xpath
     */
    public function mouseOver($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->mouseOver();
    }

    /**
     * Brings focus to element.
     *
     * @param string $xpath
     */
    public function focus($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->focus();
    }

    /**
     * Removes focus from element.
     *
     * @param string $xpath
     */
    public function blur($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->blur();
    }

    /**
     * Presses specific keyboard key.
     *
     * @param string $xpath
     * @param mixed  $char     could be either char ('b') or char-code (98)
     * @param string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyPress($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->keyPress($char, strtoupper($modifier));
    }

    /**
     * Pressed down specific keyboard key.
     *
     * @param string $xpath
     * @param mixed  $char     could be either char ('b') or char-code (98)
     * @param string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyDown($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->keyDown($char, strtoupper($modifier));
    }

    /**
     * Pressed up specific keyboard key.
     *
     * @param string $xpath
     * @param mixed  $char     could be either char ('b') or char-code (98)
     * @param string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    public function keyUp($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->keyUp($char, strtoupper($modifier));
    }

    /**
     * Drag one element onto another.
     *
     * @param string $sourceXpath
     * @param string $destinationXpath
     */
    public function dragTo($sourceXpath, $destinationXpath)
    {
        $from = $this->client->findByXPath($this->prepareXPath($sourceXpath));
        $to   = $this->client->findByXPath($this->prepareXPath($destinationXpath));

        $from->dragDrop($to);
    }

    /**
     * Executes JS script.
     *
     * @param string $script
     */
    public function executeScript($script)
    {
        $this->client->getConnection()->executeJavascript($script);
    }

    /**
     * Evaluates JS script.
     *
     * @param string $script
     *
     * @return mixed
     */
    public function evaluateScript($script)
    {
        return $this->client->getConnection()->evaluateJavascript($script);
    }

    /**
     * Waits some time or until JS condition turns true.
     *
     * @param integer $time      time in milliseconds
     * @param string  $condition JS condition
     *
     * @return boolean
     */
    public function wait($time, $condition)
    {
        return $this->client->wait($time, $condition);
    }

    /**
     * Submits the form.
     *
     * @param string $xpath Xpath.
     */
    public function submitForm($xpath)
    {
        $this->client->findByXPath($this->prepareXPath($xpath))->submitForm();
    }

    /**
     * Selects specific radio option.
     *
     * @param string $xpath xpath to one of the radio buttons
     * @param string $value value to be set
     */
    private function selectRadioOption($xpath, $value)
    {
        $name = $this->getAttribute($xpath, 'name');

        if (null !== $name) {
            $function = <<<JS
(function(){
    for (var i = 0; i < document.forms.length; i++) {
        if (document.forms[i].elements['{$name}']) {
            var form  = document.forms[i];
            var elements = form.elements['{$name}'];
            var value = elements[0].value;
            for (var f = 0; f < elements.length; f++) {
                var item = elements[f];
                if ("{$value}" == item.value) {
                    item.checked = true;

                    var event;
                    if (document.createEvent) {
                        event = document.createEvent("HTMLEvents");
                        event.initEvent("change", true, true);
                    } else {
                        event = document.createEventObject();
                        event.eventType = "change";
                    }

                    event.eventName = "change";

                    if (document.createEvent) {
                        item.dispatchEvent(event);
                    } else {
                        item.fireEvent("on" + event.eventType, event);
                    }
                }
            }
        }
    }
})()
JS;

            $this->executeScript($function);
        }
    }

    /**
     * Prepare XPath to be sent via Sahi proxy.
     *
     * @param string $xpath
     *
     * @return string
     */
    private function prepareXPath($xpath)
    {
        return substr(json_encode((string)$xpath), 1, -1);
    }

    /**
     * Removes injected by Sahi code.
     *
     * @param string $string
     *
     * @return string
     */
    private function removeSahiInjectionFromText($string)
    {
        $string = preg_replace(array(
            '/<\!--SAHI_INJECT_START--\>.*\<\!--SAHI_INJECT_END--\>/sU',
            '/\<script\>\/\*\<\!\[CDATA\[\*\/\/\*----\>\*\/__sahi.*\<\!--SAHI_INJECT_END--\>/sU'
        ), '', $string);

        $string = str_replace('/*<![CDATA[*//*---->*/__sahiDebugStr__="";__sahiDebug__=function(s){__sahiDebugStr__+=(s+"\n");};/*--*//*]]>*/ /*<![CDATA[*//*---->*/_sahi.createCookie(\'sahisid\', _sahi.sid);_sahi.loadXPathScript()/*--*//*]]>*/ /*<![CDATA[*//*---->*/eval(_sahi.sendToServer("/_s_/dyn/Player_script/script.js"));/*--*//*]]>*/ ', '', $string);

        return $string;
    }
}
