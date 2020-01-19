<?php
/**
 * Copyright (c) 1/18/20 5:06 PM Deschutes Design Group LLC.year. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

function auth0sso_config() {
    $configarray = array(
    "name" => "Auth0 Single Sign-On Integration",
    "description" => "This is a sample config function for an addon module",
    "version" => "1.0",
    "author" => "Deschutes Design Group LLC",
    "language" => 'english',
    "fields" => array(
        "option1" => array ("FriendlyName" => "Option1", "Type" => "text", "Size" => "25",
                              "Description" => "Textbox", "Default" => "Example", ),
        "option2" => array ("FriendlyName" => "Option2", "Type" => "password", "Size" => "25",
                              "Description" => "Password", ),
        "option3" => array ("FriendlyName" => "Option3", "Type" => "yesno", "Size" => "25",
                              "Description" => "Sample Check Box", ),
        "option4" => array ("FriendlyName" => "Option4", "Type" => "dropdown", "Options" =>
                              "1,2,3,4,5", "Description" => "Sample Dropdown", "Default" => "3", ),
        "option5" => array ("FriendlyName" => "Option5", "Type" => "radio", "Options" =>
                              "Demo1,Demo2,Demo3", "Description" => "Radio Options Demo", ),
        "option6" => array ("FriendlyName" => "Option6", "Type" => "textarea", "Rows" => "3",
                              "Cols" => "50", "Description" => "Description goes here", "Default" => "Test", ),
    ));
    return $configarray;
}