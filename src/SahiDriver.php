<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Mink\Driver;

use Behat\Mink\Exception\DriverException;
use Behat\SahiClient\Client;

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
            $this->executeScript(sprintf(
                '_sahi._createCookie(%s, %s)',
                json_encode($name),
                json_encode(urlencode($value))
            ));
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
            $cookieValue = $this->evaluateScript(sprintf('_sahi._cookie(%s)', json_encode($name)));

            return null === $cookieValue ? null : urldecode($cookieValue);
        } catch (DriverException $e) {
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
    public function findElementXpaths($xpath)
    {
        $jsXpath = json_encode($xpath);
        $function = <<<JS
(function(){
    var count = 0;
    while (_sahi._byXPath("("+{$jsXpath}+")["+(count+1)+"]")) count++;
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
     * {@inheritdoc}
     */
    public function getTagName($xpath)
    {
        return strtolower($this->client->findByXPath($xpath)->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getText($xpath)
    {
        return $this->removeSahiInjectionFromText(
            $this->client->findByXPath($xpath)->getText()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml($xpath)
    {
        return $this->client->findByXPath($xpath)->getHTML();
    }

    /**
     * {@inheritdoc}
     */
    public function getOuterHtml($xpath)
    {
        return $this->client->findByXPath($xpath)->getOuterHTML();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($xpath, $name)
    {
        return $this->client->findByXPath($xpath)->getAttr($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($xpath)
    {
        $tag   = $this->getTagName($xpath);
        $type  = $this->getAttribute($xpath, 'type');

        if ('radio' === $type) {
            $name = $this->getAttribute($xpath, 'name');

            if (null !== $name) {
                $name = json_encode($name);
                $function = <<<JS
(function(){
    for (var i = 0; i < document.forms.length; i++) {
        if (document.forms[i].elements[{$name}]) {
            var form  = document.forms[i];
            var elements = form.elements[{$name}];
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
            $checkbox = $this->client->findByXPath($xpath);

            return $checkbox->isChecked() ? $checkbox->getValue() : null;
        } elseif ('select' === $tag && 'multiple' === $this->getAttribute($xpath, 'multiple')) {
            $xpathEscaped = json_encode(sprintf('(%s)[1]', $xpath));

            $function = <<<JS
(function(){
    var options = [],
        node = _sahi._byXPath({$xpathEscaped});

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

        return $this->client->findByXPath($xpath)->getValue();
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
            $this->client->findByXPath($xpath)->setValue($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function check($xpath)
    {
        $this->client->findByXPath($xpath)->check();
    }

    /**
     * {@inheritdoc}
     */
    public function uncheck($xpath)
    {
        $this->client->findByXPath($xpath)->uncheck();
    }

    /**
     * {@inheritdoc}
     */
    public function isChecked($xpath)
    {
        return $this->client->findByXPath($xpath)->isChecked();
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
            $this->client->findByXPath($xpath)->choose($value, $multiple);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isSelected($xpath)
    {
        return $this->client->findByXPath($xpath)->isSelected();
    }

    /**
     * {@inheritdoc}
     */
    public function click($xpath)
    {
        $this->client->findByXPath($xpath)->click();
    }

    /**
     * {@inheritdoc}
     */
    public function doubleClick($xpath)
    {
        $this->client->findByXPath($xpath)->doubleClick();
    }

    /**
     * {@inheritdoc}
     */
    public function rightClick($xpath)
    {
        $this->client->findByXPath($xpath)->rightClick();
    }

    /**
     * {@inheritdoc}
     */
    public function attachFile($xpath, $path)
    {
        $this->client->findByXPath($xpath)->setFile($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isVisible($xpath)
    {
        return $this->client->findByXPath($xpath)->isVisible();
    }

    /**
     * {@inheritdoc}
     */
    public function mouseOver($xpath)
    {
        $this->client->findByXPath($xpath)->mouseOver();
    }

    /**
     * {@inheritdoc}
     */
    public function focus($xpath)
    {
        $this->client->findByXPath($xpath)->focus();
    }

    /**
     * {@inheritdoc}
     */
    public function blur($xpath)
    {
        $this->client->findByXPath($xpath)->blur();
    }

    /**
     * {@inheritdoc}
     */
    public function keyPress($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($xpath)->keyPress($char, strtoupper($modifier));
    }

    /**
     * {@inheritdoc}
     */
    public function keyDown($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($xpath)->keyDown($char, strtoupper($modifier));
    }

    /**
     * {@inheritdoc}
     */
    public function keyUp($xpath, $char, $modifier = null)
    {
        $this->client->findByXPath($xpath)->keyUp($char, strtoupper($modifier));
    }

    /**
     * {@inheritdoc}
     */
    public function dragTo($sourceXpath, $destinationXpath)
    {
        $from = $this->client->findByXPath($sourceXpath);
        $to   = $this->client->findByXPath($destinationXpath);

        $from->dragDrop($to);
    }

    /**
     * {@inheritdoc}
     */
    public function executeScript($script)
    {
        $script = $this->prepareScript($script);

        $this->client->getConnection()->executeJavascript($script);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluateScript($script)
    {
        $script = $this->prepareScript($script);

        return $this->client->getConnection()->evaluateJavascript($script);
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
        $this->client->findByXPath($xpath)->submitForm();
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
            $name = json_encode($name);
            $function = <<<JS
(function(){
    for (var i = 0; i < document.forms.length; i++) {
        if (document.forms[i].elements[{$name}]) {
            var form  = document.forms[i];
            var elements = form.elements[{$name}];
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
