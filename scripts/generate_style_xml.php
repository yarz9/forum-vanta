<?php
// Generate the style XML export file
$less = file_get_contents("styles/vanta/vanta_theme.less");

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<style title="Vanta" export_version="2" addon_id="XF" base_version_id="2030670">' . "\n";
$xml .= ' <assets>' . "\n";
$xml .= '  <asset key="logo" path="styles/vanta/xenforo/vanta-logo.png"/>' . "\n";
$xml .= ' </assets>' . "\n";
$xml .= ' <properties>' . "\n";
$xml .= '  <property name="buttonPrimaryBgColor" group_name="buttonPrimary" property_type="styleProp" value="#b551e6"/>' . "\n";
$xml .= '  <property name="buttonPrimaryTextColor" group_name="buttonPrimary" property_type="styleProp" value="#08070B"/>' . "\n";
$xml .= '  <property name="buttonPrimaryBorderColor" group_name="buttonPrimary" property_type="styleProp" value="#b551e6"/>' . "\n";
$xml .= '  <property name="buttonPrimaryHoverBgColor" group_name="buttonPrimary" property_type="styleProp" value="#C96AFD"/>' . "\n";
$xml .= '  <property name="navTextColor" group_name="nav" property_type="styleProp" value="#A79BAE"/>' . "\n";
$xml .= '  <property name="navHoverBgColor" group_name="nav" property_type="styleProp" value="rgba(233, 147, 250, 0.08)"/>' . "\n";
$xml .= '  <property name="navSelectedBgColor" group_name="nav" property_type="styleProp" value="rgba(233, 147, 250, 0.12)"/>' . "\n";
$xml .= '  <property name="navBarAccentColor" group_name="navBar" property_type="styleProp" value="#b551e6"/>' . "\n";
$xml .= '  <property name="topBarTextColor" group_name="topBar" property_type="styleProp" value="#A79BAE"/>' . "\n";
$xml .= '  <property name="topBarLinkColor" group_name="topBar" property_type="styleProp" value="#A79BAE"/>' . "\n";
$xml .= '  <property name="topBarLinkHoverColor" group_name="topBar" property_type="styleProp" value="#E993FA"/>' . "\n";
$xml .= '  <property name="topBarBgColor" group_name="topBar" property_type="styleProp" value="#17121C"/>' . "\n";
$xml .= '  <property name="subNavBgColor" group_name="subNav" property_type="styleProp" value="#17121C"/>' . "\n";
$xml .= '  <property name="subNavLinkColor" group_name="subNav" property_type="styleProp" value="#A79BAE"/>' . "\n";
$xml .= '  <property name="subNavLinkHoverColor" group_name="subNav" property_type="styleProp" value="#F3B8FF"/>' . "\n";
$xml .= '  <property name="subNavLinkSelectedColor" group_name="subNav" property_type="styleProp" value="#E993FA"/>' . "\n";
$xml .= ' </properties>' . "\n";
$xml .= ' <templates>' . "\n";
$xml .= '  <template title="extra.less" type="public" addon_id="XF" version_id="0" version_string="0.0.0">' . "\n";
$xml .= '<![CDATA[' . $less . "]]>\n";
$xml .= "  </template>\n";
$xml .= " </templates>\n";
$xml .= "</style>\n";

file_put_contents("styles/vanta/export-vanta.xml", $xml);
echo "XML file generated: styles/vanta/export-vanta.xml (" . strlen($xml) . " bytes)\n";