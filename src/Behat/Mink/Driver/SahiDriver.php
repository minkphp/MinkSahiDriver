<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Mink\Driver;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Session;
use Behat\SahiClient\Client;
use Behat\SahiClient\Exception\ConnectionException;

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
    private $session;

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
     * {@inheritdoc}
     */
    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->client->start($this->browserName);
        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->client->stop();
        $this->started = false;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function visit($url)
    {
        $this->client->navigateTo($url, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUrl()
    {
        return $this->evaluateScript('document.URL');
    }

    /**
     * {@inheritdoc}
     */
    public function reload()
    {
        $this->visit($this->getCurrentUrl());
    }

    /**
     * {@inheritdoc}
     */
    public function forward()
    {
        $this->executeScript('history.forward()');
    }

    /**
     * {@inheritdoc}
     */
    public function back()
    {
        $this->executeScript('history.back()');
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getContent()
    {
        $html = $this->evaluateScript('document.getElementsByTagName("html")[0].innerHTML');
        $html = $this->removeSahiInjectionFromText($html);

        return "<html>\n$html\n</html>";
    }

    /**
     * {@inheritdoc}
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
            $elements[] = new NodeElement(sprintf('(%s)[%d]', $xpath, $i + 1), $this->session);
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagName($xpath)
    {
        try {
            return strtolower($this->client->findByXPath($xpath)->getName());
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the tag name', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getText($xpath)
    {
        try {
            return $this->removeSahiInjectionFromText(
                $this->client->findByXPath($xpath)->getText()
            );
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the text', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml($xpath)
    {
        try {
            return $this->client->findByXPath($xpath)->getHTML();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the HTML', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($xpath, $name)
    {
        try {
            return $this->client->findByXPath($xpath)->getAttr($name);
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the attribute', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($xpath)
    {
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
            $xpathEscaped = $this->prepareXPath($xpath);

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
            }

            return explode(',', $value);
        }

        try {
            return $this->client->findByXPath($xpath)->getValue();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the value', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($xpath, $value)
    {
        $type = $this->getAttribute($xpath, 'type');

        if ('radio' === $type) {
            $this->selectRadioOption($xpath, $value);
        } elseif ('checkbox' === $type) {
            if ((boolean) $value) {
                $this->check($xpath);
            } else {
                $this->uncheck($xpath);
            }
        } else {
            try {
                $this->client->findByXPath($xpath)->setValue($value);
            } catch (ConnectionException $e) {
                throw new DriverException('An error happened while setting the value', 0, $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function check($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->check();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while checking the checkbox', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function uncheck($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->uncheck();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while unchecking the checkbox', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isChecked($xpath)
    {
        try {
            return $this->client->findByXPath($xpath)->isChecked();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the state of the checkbox', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function selectOption($xpath, $value, $multiple = false)
    {
        $type = $this->getAttribute($xpath, 'type');

        if ('radio' === $type) {
            $this->selectRadioOption($xpath, $value);
        } else {
            try {
                $this->client->findByXPath($xpath)->choose($value, $multiple);
            } catch (ConnectionException $e) {
                throw new DriverException('An error happened while choosing an option', 0, $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isSelected($xpath)
    {
        try {
            return $this->client->findByXPath($xpath)->isSelected();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the state of an option', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function click($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->click();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while clicking', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doubleClick($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->doubleClick();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while double clicking', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rightClick($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->rightClick();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while right clicking', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attachFile($xpath, $path)
    {
        try {
            $this->client->findByXPath($xpath)->setFile($path);
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while setting the file', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isVisible($xpath)
    {
        try {
            return $this->client->findByXPath($xpath)->isVisible();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while getting the visibility', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mouseOver($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->mouseOver();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while mouving the mouse over the element', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function focus($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->focus();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while focusing the element', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function blur($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->blur();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while blurring the element', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function keyPress($xpath, $char, $modifier = null)
    {
        try {
            $this->client->findByXPath($xpath)->keyPress($char, strtoupper($modifier));
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while pressing a key', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function keyDown($xpath, $char, $modifier = null)
    {
        try {
            $this->client->findByXPath($xpath)->keyDown($char, strtoupper($modifier));
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while pressing a key', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function keyUp($xpath, $char, $modifier = null)
    {
        try {
            $this->client->findByXPath($xpath)->keyUp($char, strtoupper($modifier));
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while pressing a key', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dragTo($sourceXpath, $destinationXpath)
    {
        $from = $this->client->findByXPath($sourceXpath);
        $to   = $this->client->findByXPath($destinationXpath);

        try {
            $from->dragDrop($to);
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while dragging the element', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeScript($script)
    {
        $script = $this->prepareScript($script);

        try {
            $this->client->getConnection()->executeJavascript($script);
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while executing the script', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function evaluateScript($script)
    {
        $script = $this->prepareScript($script);

        try {
            return $this->client->getConnection()->evaluateJavascript($script);
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while evaluating the script', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function wait($timeout, $condition)
    {
        return $this->client->wait($timeout, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm($xpath)
    {
        try {
            $this->client->findByXPath($xpath)->submitForm();
        } catch (ConnectionException $e) {
            throw new DriverException('An error happened while submitting the form', 0, $e);
        }
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
     * Prepare script to be sent via Sahi proxy.
     *
     * @param string $script
     *
     * @return string
     */
    private function prepareScript($script)
    {
        $script = preg_replace('/^return\s+/', '', $script);
        $script = preg_replace('/;$/', '', $script);

        return $script;
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
