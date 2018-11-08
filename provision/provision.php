<?php
/**
 * Copyright (C) 2018 Drew Gauderman <drew@dpg.host>

 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

//set time zone
date_default_timezone_set('America/Los_Angeles');

//response type will be XML code
header('Content-type: application/xml');

//get the mac address from URL
$mac = $_GET['mac'] ?? '';

//file path to the device config file
$fileName = "devices/cfg$mac.xml";

//if the device is checking in, record a log of it
if (!empty($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'], 'Grandstream') !== false) {
    //builds an array: 1: Model, 2: Hardware Version, 3: Software Version
    preg_match('/HW\s(.+)\sV(.*)\sSW\s(.+)\sDevId/', $_SERVER['HTTP_USER_AGENT'], $deviceInfo);
}

//no device information found from HTTP_USER_AGENT, not an ATA checking in...
if (empty($deviceInfo) && !file_exists($fileName)) {
    die('<xml />');
}

//cant do anything if file doesnt exist
if (!empty($deviceInfo)) {
    $model = strtolower($deviceInfo[1]);

    //config file doesnt exist yet, lets make one
    if (!file_exists($fileName)) {
        //get model name
        $model = strtolower($deviceInfo[1]);

        //no base file to base this ATA.
        if (!file_exists($model.'_blank.xml')) {
            die();
        }

        //the new config file and the base file to make
        $file = file_get_contents($model.'_blank.xml');
        $file = preg_replace('/<mac>(.*)<\/mac>/', "<mac>$mac</mac>", $file);

        file_put_contents($fileName, $file);
    }
}

//Last-Modified tells the ATA device the last time the xml file changed so it doesnt download on every check
header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($fileName)).' GMT', true, 200);

//get the config file
$configXML = json_decode(json_encode(simplexml_load_string(file_get_contents($fileName))), true);

//remove comments
unset($configXML['config']['comment']);

//merge base with xml and save log of ata checking in
if (!empty($deviceInfo)) {
    //load default config file
    $file = file_get_contents($model.'_base.xml', true);

    //get the base default config for this model of ATA (global settings)
    $defaultConfig = json_decode(json_encode(simplexml_load_string($file)), true);

    //remove unnessary stuff from config file to reduce its size
    unset($defaultConfig['config']['@attributes']);
    unset($defaultConfig['config']['comment']);

    //update mac field
    $defaultConfig['mac'] = $mac;

    //merge default configs with cfg.xml file
    $configXML['config'] = $configXML['config'] + $defaultConfig['config'];

    //get log info for device checking in
    $checkIns = json_decode(file_get_contents('checkins.txt'), true);

    //checkin data
    $checkIns[$mac] = $checkIns[$mac] ?? [];
    $checkIns[$mac]['name'] = $configXML['config']['P146'] ?? 'unknown';
    $checkIns[$mac]['ip'] = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $checkIns[$mac]['model'] = $deviceInfo[1] ?? 'unknown';
    $checkIns[$mac]['hardware_version'] = $deviceInfo[2] ?? 'unknown';
    $checkIns[$mac]['software_version'] = $deviceInfo[3] ?? 'unknown';
    $checkIns[$mac]['checkins'] = $checkIns[$mac]['checkins'] ?? [];
    $checkIns[$mac]['checkins'][] = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time());

    //save log in json format
    file_put_contents('checkins.txt', json_encode($checkIns, JSON_PRETTY_PRINT));
}

// creating object of SimpleXMLElement
$xmlData = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gs_provision/>');

// function call to convert array to xml
array_to_xml($configXML, $xmlData);

//output the merged xml
echo $xmlData->asXML();

// function defination to convert array to xml
function array_to_xml($data, &$xml_data)
{
    foreach ($data as $key => $value) {
        if (is_numeric($key)) {
            $key = 'item'.$key; //dealing with <0/>..<n/> issues
        }
        if (is_array($value)) {
            if (strpos($key, '@') === false) {
                $subnode = $xml_data->addChild($key);
                array_to_xml($value, $subnode);
            } else {
                foreach ($value as $name => $attr) {
                    $subnode = $xml_data->addAttribute($name, $attr);
                }
            }
        } else {
            $xml_data->addChild("$key", htmlspecialchars("$value"));
        }
    }
}
