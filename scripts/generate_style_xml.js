const fs = require('fs');
const path = require('path');

const lessContent = fs.readFileSync(path.join(__dirname, '../styles/vanta/vanta_theme.less'), 'utf8');

let xml = `<?xml version="1.0" encoding="UTF-8"?>
<style title="Vanta" export_version="2" addon_id="XF" base_version_id="2030670">
 <assets>
  <asset key="logo" path="styles/vanta/xenforo/vanta-logo.png"/>
 </assets>
 <properties>
  <property name="buttonPrimaryBgColor" group_name="buttonPrimary" property_type="styleProp" value="#b551e6"/>
  <property name="buttonPrimaryTextColor" group_name="buttonPrimary" property_type="styleProp" value="#08070B"/>
  <property name="buttonPrimaryBorderColor" group_name="buttonPrimary" property_type="styleProp" value="#b551e6"/>
  <property name="buttonPrimaryHoverBgColor" group_name="buttonPrimary" property_type="styleProp" value="#C96AFD"/>
  <property name="navTextColor" group_name="nav" property_type="styleProp" value="#A79BAE"/>
  <property name="navHoverBgColor" group_name="nav" property_type="styleProp" value="rgba(233, 147, 250, 0.08)"/>
  <property name="navSelectedBgColor" group_name="nav" property_type="styleProp" value="rgba(233, 147, 250, 0.12)"/>
  <property name="navBarAccentColor" group_name="navBar" property_type="styleProp" value="#b551e6"/>
  <property name="topBarTextColor" group_name="topBar" property_type="styleProp" value="#A79BAE"/>
  <property name="topBarLinkColor" group_name="topBar" property_type="styleProp" value="#A79BAE"/>
  <property name="topBarLinkHoverColor" group_name="topBar" property_type="styleProp" value="#E993FA"/>
  <property name="topBarBgColor" group_name="topBar" property_type="styleProp" value="#17121C"/>
  <property name="subNavBgColor" group_name="subNav" property_type="styleProp" value="#17121C"/>
  <property name="subNavLinkColor" group_name="subNav" property_type="styleProp" value="#A79BAE"/>
  <property name="subNavLinkHoverColor" group_name="subNav" property_type="styleProp" value="#F3B8FF"/>
  <property name="subNavLinkSelectedColor" group_name="subNav" property_type="styleProp" value="#E993FA"/>
 </properties>
 <templates>
  <template title="extra.less" type="public" addon_id="XF" version_id="0" version_string="0.0.0">
<![CDATA[
${lessContent}
]]>
  </template>
 </templates>
</style>
`;

fs.writeFileSync(path.join(__dirname, '../styles/vanta/vanta_style.xml'), xml, 'utf8');
console.log('Style XML generated successfully!');